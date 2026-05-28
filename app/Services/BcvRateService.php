<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\RateHistory;
use App\Models\Settings;
use DOMDocument;
use DOMXPath;
use RuntimeException;

class BcvRateService
{
    private const TIMEZONE = 'America/Caracas';
    private const CACHE_TTL = 300;
    private const MIN_SAFE_RATE = 10.0;
    private const MAX_SAFE_RATE = 1000.0;

    public function resolve(?string $date = null, bool $forceRefresh = false): array
    {
        return $this->resolveForSettings((new Settings())->getAllKeyed(), $date, $forceRefresh);
    }

    public function preview(string $mode, string $customCurrency = 'USD', float $customRate = 0.0, ?string $date = null, bool $forceRefresh = false): array
    {
        return $this->resolveForSettings([
            'exchange_rate_mode' => $mode,
            'exchange_rate_custom_currency' => $customCurrency,
            'exchange_rate_custom' => (string) $customRate,
            'currency_secondary' => 'VES',
            'default_exchange_rate' => (string) ($customRate > 0 ? $customRate : default_exchange_rate()),
        ], $date, $forceRefresh);
    }

    public function syncConfiguredRate(bool $forceRefresh = false): array
    {
        $resolved = $this->resolve(date('Y-m-d'), $forceRefresh);
        $settings = new Settings();
        $settings->set('default_exchange_rate', (string) $resolved['rate']);
        $settings->set('currency_base', 'USD');
        $settings->set('currency_secondary', 'VES');

        return $resolved;
    }

    public function currentMeta(?array $settings = null, ?array $resolved = null): array
    {
        $settings ??= (new Settings())->getAllKeyed();
        $mode = $this->configuredMode($settings);
        $cache = $this->readCache();

        return [
            'mode' => $mode,
            'source' => (string) ($resolved['source'] ?? $cache['source'] ?? $this->sourceLabel($mode)),
            'currency_from' => (string) ($resolved['currency_from'] ?? $this->configuredAnchorCurrency($settings)),
            'currency_to' => (string) ($resolved['currency_to'] ?? strtoupper(trim((string) ($settings['currency_secondary'] ?? secondary_currency())))),
            'rate' => (float) ($resolved['rate'] ?? $settings['default_exchange_rate'] ?? default_exchange_rate()),
            'date' => (string) ($resolved['date'] ?? $cache['date'] ?? date('Y-m-d')),
            'fetched_at' => (int) ($cache['fetched_at'] ?? 0),
            'errors' => is_array($cache['errors'] ?? null) ? $cache['errors'] : [],
        ];
    }

    private function resolveCustomRate(array $settings, string $today): array
    {
        $rate = (float) ($settings['exchange_rate_custom'] ?? $settings['default_exchange_rate'] ?? default_exchange_rate());
        if ($rate <= 0) {
            throw new RuntimeException('La tasa personalizada debe ser mayor a cero.');
        }

        return [
            'source' => 'Tasa personalizada',
            'currency_from' => $this->configuredAnchorCurrency($settings),
            'currency_to' => strtoupper(trim((string) ($settings['currency_secondary'] ?? secondary_currency()))),
            'rate' => $rate,
            'date' => $today,
        ];
    }

    private function resolveBcvRate(string $mode, string $today, bool $forceRefresh): array
    {
        $rates = $this->scrapeRates($forceRefresh);
        $key = $mode === 'bcv_eur' ? 'EUR' : 'USD';

        if (!isset($rates[$key]['rate'])) {
            throw new RuntimeException('No se pudo resolver la tasa BCV para ' . $key . '.');
        }

        return [
            'source' => 'BCV oficial',
            'currency_from' => $key,
            'currency_to' => 'VES',
            'rate' => (float) $rates[$key]['rate'],
            'date' => (string) ($rates[$key]['date'] ?? $today),
        ];
    }

    private function resolveForSettings(array $settings, ?string $date, bool $forceRefresh): array
    {
        $mode = $this->configuredMode($settings);
        $anchorCurrency = $this->configuredAnchorCurrency($settings);
        $targetCurrency = strtoupper(trim((string) ($settings['currency_secondary'] ?? secondary_currency())));
        $requestedDate = $this->normalizeDate($date) ?? date('Y-m-d');
        $today = date('Y-m-d');
        $shouldScrapeLive = $mode !== 'custom' && $requestedDate === $today;

        $history = new RateHistory();
        $historicalRate = $history->forPairOnDate($requestedDate, $anchorCurrency, $targetCurrency);

        if ($historicalRate && !$forceRefresh && !$shouldScrapeLive) {
            return [
                'mode' => $mode,
                'currency_from' => (string) ($historicalRate['currency_from'] ?? $anchorCurrency),
                'currency_to' => (string) ($historicalRate['currency_to'] ?? $targetCurrency),
                'rate' => (float) ($historicalRate['rate'] ?? default_exchange_rate()),
                'date' => (string) ($historicalRate['rate_date'] ?? $requestedDate),
                'source' => $this->sourceLabel($mode),
                'meta' => $this->currentMeta($settings),
            ];
        }

        if ($requestedDate !== $today && $historicalRate) {
            return [
                'mode' => $mode,
                'currency_from' => (string) ($historicalRate['currency_from'] ?? $anchorCurrency),
                'currency_to' => (string) ($historicalRate['currency_to'] ?? $targetCurrency),
                'rate' => (float) ($historicalRate['rate'] ?? default_exchange_rate()),
                'date' => (string) ($historicalRate['rate_date'] ?? $requestedDate),
                'source' => $this->sourceLabel($mode),
                'meta' => $this->currentMeta($settings),
            ];
        }

        $resolved = $mode === 'custom'
            ? $this->resolveCustomRate($settings, $today)
            : $this->resolveBcvRate($mode, $today, $forceRefresh);

        $history->upsertDaily(
            $resolved['date'],
            $resolved['currency_from'],
            $resolved['currency_to'],
            $resolved['rate']
        );

        return [
            'mode' => $mode,
            'currency_from' => $resolved['currency_from'],
            'currency_to' => $resolved['currency_to'],
            'rate' => $resolved['rate'],
            'date' => $resolved['date'],
            'source' => $resolved['source'],
            'meta' => $this->currentMeta($settings, $resolved),
        ];
    }

    private function scrapeRates(bool $forceRefresh = false): array
    {
        $cache = $this->readCache();
        if (!$forceRefresh && $cache && isset($cache['fetched_at']) && (int) $cache['fetched_at'] > (time() - self::CACHE_TTL)) {
            return (array) ($cache['rates'] ?? []);
        }

        try {
            $html = $this->httpGet('https://www.bcv.org.ve/', 15, false);

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loaded = @$dom->loadHTML($html);
            libxml_clear_errors();

            if (!$loaded) {
                throw new RuntimeException('No se pudo procesar la respuesta del BCV.');
            }

            $xpath = new DOMXPath($dom);
            $dateNodes = $xpath->query('//span[contains(@class,"date-display-single")]');
            $dateValue = $dateNodes->length > 0 ? trim((string) $dateNodes->item(0)?->nodeValue) : date('Y-m-d');
            $normalizedDate = $this->normalizeDate($dateValue) ?? date('Y-m-d');

            $rates = [
                'USD' => [
                    'rate' => $this->extractRate($xpath, 'dolar'),
                    'date' => $normalizedDate,
                ],
                'EUR' => [
                    'rate' => $this->extractRate($xpath, 'euro'),
                    'date' => $normalizedDate,
                ],
            ];

            foreach ($rates as $currency => $payload) {
                $rate = (float) ($payload['rate'] ?? 0);
                if ($rate < self::MIN_SAFE_RATE || $rate > self::MAX_SAFE_RATE) {
                    throw new RuntimeException("La tasa {$currency} del BCV quedo fuera de rango: {$rate}");
                }
            }

            $this->writeCache([
                'fetched_at' => time(),
                'date' => $normalizedDate,
                'source' => 'BCV oficial',
                'rates' => $rates,
                'errors' => [],
            ]);

            return $rates;
        } catch (\Throwable $exception) {
            if (is_array($cache['rates'] ?? null) && $cache['rates'] !== []) {
                $cache['errors'] = array_values(array_filter(array_merge(
                    is_array($cache['errors'] ?? null) ? $cache['errors'] : [],
                    [$exception->getMessage()]
                )));
                $cache['source'] = (string) ($cache['source'] ?? 'Cache local');
                $this->writeCache($cache);

                return (array) $cache['rates'];
            }

            throw $exception;
        }
    }

    private function extractRate(DOMXPath $xpath, string $nodeId): float
    {
        $nodes = $xpath->query(sprintf('//div[@id="%s"]//strong', $nodeId));
        if ($nodes->length === 0) {
            throw new RuntimeException('No se encontro el nodo ' . $nodeId . ' en el BCV.');
        }

        $raw = trim((string) $nodes->item(0)?->nodeValue);
        $normalized = str_replace(',', '.', preg_replace('/[^0-9,\.]/', '', $raw) ?? '');
        $rate = round((float) $normalized, 4);

        if ($rate <= 0) {
            throw new RuntimeException('La tasa extraida del BCV es invalida para ' . strtoupper($nodeId) . '.');
        }

        return $rate;
    }

    private function httpGet(string $url, int $timeout = 10, bool $verifySsl = true): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL no esta disponible en este servidor.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('No se pudo inicializar cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: no-cache',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('HTTP ' . $httpCode);
        }

        return (string) $body;
    }

    private function configuredMode(array $settings): string
    {
        $mode = strtolower(trim((string) ($settings['exchange_rate_mode'] ?? 'bcv_usd')));
        return in_array($mode, ['bcv_usd', 'bcv_eur', 'custom'], true) ? $mode : 'bcv_usd';
    }

    private function configuredAnchorCurrency(array $settings): string
    {
        $mode = $this->configuredMode($settings);

        if ($mode === 'bcv_eur') {
            return 'EUR';
        }

        if ($mode === 'custom') {
            $customCurrency = strtoupper(trim((string) ($settings['exchange_rate_custom_currency'] ?? 'USD')));
            return in_array($customCurrency, ['USD', 'EUR'], true) ? $customCurrency : 'USD';
        }

        return 'USD';
    }

    private function sourceLabel(string $mode): string
    {
        return match ($mode) {
            'bcv_eur' => 'BCV euro',
            'custom' => 'Tasa personalizada',
            default => 'BCV dolar',
        };
    }

    private function cacheFile(): string
    {
        return dirname(__DIR__, 2) . '/database/bcv_rate_cache.json';
    }

    private function readCache(): ?array
    {
        $file = $this->cacheFile();
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(array $payload): void
    {
        $file = $this->cacheFile();
        $directory = dirname($file);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        file_put_contents($file, $json, LOCK_EX);
    }

    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $months = [
            'Enero' => 'January',
            'Febrero' => 'February',
            'Marzo' => 'March',
            'Abril' => 'April',
            'Mayo' => 'May',
            'Junio' => 'June',
            'Julio' => 'July',
            'Agosto' => 'August',
            'Septiembre' => 'September',
            'Octubre' => 'October',
            'Noviembre' => 'November',
            'Diciembre' => 'December',
        ];

        $normalized = str_ireplace(array_keys($months), array_values($months), $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'l, j F Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $normalized, new \DateTimeZone(self::TIMEZONE));
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTimeImmutable($normalized, new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}

