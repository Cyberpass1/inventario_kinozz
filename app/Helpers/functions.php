<?php
declare(strict_types=1);

use App\Core\CSRF;
use App\Core\Database;
use App\Services\BcvRateService;

function env(string $key, mixed $default = null): mixed {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true' => true,
        'false' => false,
        default => $value,
    };
}

function e(string|null $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function flash(string $key, ?string $value = null): ?string { if ($value !== null) { $_SESSION['_flash'][$key] = $value; return null; } $m = $_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $m; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . CSRF::token() . '">'; }
function validate_csrf(): void { if (!CSRF::validate($_POST['_csrf'] ?? null)) { http_response_code(419); exit('CSRF invalido'); } }
function auth_user(): ?array { return \App\Core\Auth::user(); }
function money(float|int|string $amount): string { return number_format((float) $amount, 2, ',', '.'); }
function parse_money_input(mixed $value): float
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return 0.0;
    }

    $normalized = str_replace(["\xc2\xa0", ' '], '', $raw);
    $lastComma = strrpos($normalized, ',');
    $lastDot = strrpos($normalized, '.');

    if ($lastComma !== false && $lastDot !== false) {
        if ($lastComma > $lastDot) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($lastComma !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (substr_count($normalized, '.') > 1) {
        $normalized = str_replace('.', '', $normalized);
    }

    $normalized = preg_replace('/[^0-9.\-]/', '', $normalized) ?? '';

    return is_numeric($normalized) ? (float) $normalized : 0.0;
}

function round_money(float|int|string $amount): float
{
    return round((float) $amount, 2);
}

function payment_rounding_tolerance(): float
{
    return 0.10;
}

function money_difference(float|int|string $left, float|int|string $right): float
{
    return round_money(round_money($left) - round_money($right));
}

function payment_exceeds_balance(
    float|int|string $appliedAmount,
    float|int|string $availableBalance,
    float|int|string|null $tolerance = null
): bool {
    return money_difference($appliedAmount, $availableBalance) > round_money($tolerance ?? payment_rounding_tolerance());
}

function company(): array { return ['name' => env('COMPANY_NAME', 'Empresa'), 'rif' => env('COMPANY_RIF', ''), 'address' => env('COMPANY_ADDRESS', ''), 'phones' => env('COMPANY_PHONES', ''), 'email' => env('COMPANY_EMAIL', ''), 'web' => env('COMPANY_WEB', '')]; }

function app_settings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    try {
        $rows = Database::connection()->query('SELECT key_name, value FROM settings')->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row['key_name']] = $row['value'];
        }
    } catch (\Throwable) {
        $settings = [];
    }

    return $settings;
}

function setting(string $key, mixed $default = null): mixed
{
    $settings = app_settings();
    return $settings[$key] ?? $default;
}

function base_currency(): string { return (string) setting('currency_base', env('CURRENCY_BASE', 'USD')); }
function secondary_currency(): string { return (string) setting('currency_secondary', env('CURRENCY_SECONDARY', 'VES')); }
function default_exchange_rate(): float { return (float) setting('default_exchange_rate', env('DEFAULT_EXCHANGE_RATE', 1)); }
function tax_percent(): float { return (float) setting('tax_percent', env('TAX_PERCENT', 16)); }
function invoice_due_days(): int { return max(0, (int) setting('invoice_due_days', env('INVOICE_DUE_DAYS', 10))); }
function production_enabled(): bool {
    $value = setting('production_enabled', '1');
    if ($value === null || $value === '') {
        return true;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'si'], true);
}
function purchase_due_days(): int { return max(0, (int) setting('purchase_due_days', env('PURCHASE_DUE_DAYS', 10))); }
function document_due_date(string $documentDate, int $days): string
{
    $date = \DateTime::createFromFormat('Y-m-d', $documentDate) ?: new \DateTime();

    if ($days > 0) {
        $date->modify('+' . $days . ' days');
    }

    return $date->format('Y-m-d');
}
function payment_method_options(): array
{
    return [
        'point_of_sale' => 'Punto de venta',
        'cash' => 'Efectivo',
        'bank_transfer' => 'Transferencia',
        'mobile_payment' => 'Pago movil',
        'usdt' => 'USDT',
        'zelle' => 'Zelle',
    ];
}
function payment_method_label(string|null $method): string
{
    $method = strtolower(trim((string) $method));
    $options = payment_method_options();
    return $options[$method] ?? 'Metodo no definido';
}
function payment_method_forced_currency(string|null $method): ?string
{
    $method = strtolower(trim((string) $method));

    if (in_array($method, ['point_of_sale', 'bank_transfer', 'mobile_payment'], true)) {
        return normalize_currency_code(secondary_currency());
    }

    if (in_array($method, ['usdt', 'zelle'], true)) {
        return normalize_currency_code(base_currency());
    }

    return null;
}
function product_type_options(): array
{
    return [
        'merchandise' => 'Producto',
        'raw_material' => 'Materia prima',
        'finished_good' => 'Producto fabricado',
        'service' => 'Servicio',
    ];
}
function product_type_label(string|null $type): string
{
    $type = strtolower(trim((string) $type));
    $options = product_type_options();

    return $options[$type] ?? 'Producto';
}
function product_purchasable_types(): array
{
    return ['merchandise', 'raw_material', 'finished_good'];
}
function product_saleable_types(): array
{
    return ['merchandise', 'finished_good', 'service'];
}
function product_manufacturable_types(): array
{
    return ['finished_good', 'merchandise'];
}
function product_stock_managed_types(): array
{
    return ['merchandise', 'raw_material', 'finished_good'];
}
function product_tracks_inventory(array|string|null $product): bool
{
    $type = is_array($product)
        ? (string) ($product['product_type'] ?? 'merchandise')
        : (string) $product;

    return in_array(strtolower(trim($type)), product_stock_managed_types(), true);
}
function product_is_purchasable(array|string|null $product): bool
{
    $type = is_array($product)
        ? (string) ($product['product_type'] ?? 'merchandise')
        : (string) $product;

    return in_array(strtolower(trim($type)), product_purchasable_types(), true);
}
function product_is_saleable(array|string|null $product): bool
{
    $type = is_array($product)
        ? (string) ($product['product_type'] ?? 'merchandise')
        : (string) $product;

    return in_array(strtolower(trim($type)), product_saleable_types(), true);
}
function product_display_name(string|null $name, string|null $category = null): string
{
    $name = trim((string) $name);
    $category = trim((string) $category);

    if ($name === '' && $category === '') {
        return 'Producto sin nombre';
    }

    if ($category === '') {
        return $name;
    }

    if ($name === '') {
        return 'Sin nombre - ' . $category;
    }

    return $name . ' - ' . $category;
}
function product_unit_suggestions(): array
{
    return ['und', 'm', 'cm', 'kg', 'g', 'l', 'ml', 'rollo', 'cono', 'par', 'juego', 'serv'];
}
function default_product_unit(string|null $type = null): string
{
    $normalizedType = strtolower(trim((string) $type));

    return $normalizedType === 'service' ? 'serv' : 'und';
}
function normalize_product_unit(string|null $unit, string|null $type = null): string
{
    $normalized = strtolower(trim((string) $unit));
    if ($normalized === '') {
        return default_product_unit($type);
    }

    return substr($normalized, 0, 20);
}
function product_unit_label(array|string|null $product, ?string $fallbackType = null): string
{
    if (is_array($product)) {
        return normalize_product_unit((string) ($product['unit_label'] ?? ''), (string) ($product['product_type'] ?? $fallbackType ?? ''));
    }

    return normalize_product_unit((string) $product, $fallbackType);
}
function treasury_account_label(string|null $method, string|null $currency): string
{
    $method = strtolower(trim((string) $method));
    $currency = normalize_currency_code($currency);

    return match ($method) {
        'cash' => 'Caja ' . $currency,
        'point_of_sale' => 'Punto de venta ' . $currency,
        'bank_transfer' => 'Transferencia ' . $currency,
        'mobile_payment' => 'Pago movil ' . $currency,
        'usdt' => 'Billetera USDT ' . $currency,
        'zelle' => 'Zelle ' . $currency,
        default => payment_method_label($method) . ' ' . $currency,
    };
}
function treasury_account_code(string|null $method, string|null $currency): string
{
    $method = strtolower(trim((string) $method));
    $currency = normalize_currency_code($currency);
    $methodCode = match ($method) {
        'cash' => '11',
        'point_of_sale' => '12',
        'bank_transfer' => '13',
        'mobile_payment' => '14',
        'usdt' => '15',
        'zelle' => '16',
        default => '19',
    };
    $currencyCode = is_bolivar_currency($currency) ? '02' : '01';

    return '101' . $methodCode . $currencyCode;
}
function amount_to_reporting_currency(float|int|string $amount, string|null $currency, float|int|string|null $rate = null): float
{
    return equivalent_in_bolivars($amount, $currency, $rate);
}
function amount_to_reference_currency(float|int|string $amount, string|null $currency, float|int|string|null $rate = null): float
{
    return convert_currency_amount($amount, $currency, base_currency(), $rate);
}
function default_payment_reference(string|null $method, string $prefix = 'Pago'): string
{
    return strtoupper(trim($prefix . ' ' . payment_method_label($method)));
}
function normalize_currency_code(string|null $currency): string
{
    return strtoupper(trim((string) $currency));
}

function is_bolivar_currency(string|null $currency): bool
{
    return in_array(normalize_currency_code($currency), ['VES', 'VEF', 'BS', 'BS.S', 'BSS', 'BOLIVARES'], true);
}

function convert_currency_amount(float|int|string $amount, string|null $fromCurrency, string|null $toCurrency, float|int|string|null $rate = null): float
{
    $numericAmount = (float) $amount;
    $numericRate = (float) ($rate ?? 0);
    $from = normalize_currency_code($fromCurrency);
    $to = normalize_currency_code($toCurrency);

    if ($numericAmount === 0.0 || $from === '' || $to === '' || $from === $to) {
        return $numericAmount;
    }

    if ($numericRate <= 0) {
        return 0.0;
    }

    if (is_bolivar_currency($from) && !is_bolivar_currency($to)) {
        return $numericAmount / $numericRate;
    }

    if (!is_bolivar_currency($from) && is_bolivar_currency($to)) {
        return $numericAmount * $numericRate;
    }

    return $numericAmount;
}

function system_exchange_rate(?string $date = null): float
{
    try {
        $resolved = (new BcvRateService())->resolve($date);
        return (float) ($resolved['rate'] ?? default_exchange_rate());
    } catch (\Throwable) {
        return default_exchange_rate();
    }
}

function equivalent_in_bolivars(float|int|string $amount, string|null $currency, float|int|string|null $rate = null): float
{
    return convert_currency_amount($amount, $currency, secondary_currency(), $rate);
}

function expense_currency_breakdown(
    float|int|string $amount,
    string|null $currency,
    float|int|string|null $rate = null
): array {
    $normalizedCurrency = normalize_currency_code($currency);
    $exchangeRate = (float) ($rate ?? 0);
    $originalAmount = round_money($amount);
    $consolidatedAmount = round_money(equivalent_in_bolivars($originalAmount, $normalizedCurrency, $exchangeRate));
    $referenceAmount = round_money(convert_currency_amount($originalAmount, $normalizedCurrency, base_currency(), $exchangeRate));

    return [
        'currency_code' => $normalizedCurrency,
        'exchange_rate' => $exchangeRate,
        'amount_original' => $originalAmount,
        'amount_consolidated' => $consolidatedAmount,
        'amount_reference' => $referenceAmount,
        'consolidated_currency' => secondary_currency(),
        'reference_currency' => base_currency(),
    ];
}

function app_base_path(): string {
    static $basePath = null;
    if ($basePath !== null) return $basePath;

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = trim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($scriptDir !== '' && $scriptDir !== '.') {
        $basePath = '/' . $scriptDir;
        return $basePath;
    }

    $path = (string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH);
    $path = '/' . trim($path, '/');
    $basePath = $path === '/' ? '' : $path;
    return $basePath;
}

function app_url(string $path = '/'): string {
    if ($path === '') $path = '/';
    if (preg_match('#^https?://#i', $path) === 1) return $path;
    $normalized = '/' . ltrim($path, '/');
    if ($normalized === '/') return app_base_path() ?: '/';
    return (app_base_path() ?: '') . $normalized;
}

function asset_url(string $path = ''): string {
    static $assetsBase = null;

    if ($assetsBase === null) {
        $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), "\\/");
        $publicAssetPath = $documentRoot !== '' ? $documentRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'assets' : '';
        $rootAssetPath = $documentRoot !== '' ? $documentRoot . DIRECTORY_SEPARATOR . 'assets' : '';

        if ($rootAssetPath !== '' && is_dir($rootAssetPath)) {
            $assetsBase = app_url('/assets');
        } elseif ($publicAssetPath !== '' && is_dir($publicAssetPath)) {
            $assetsBase = app_url('/public/assets');
        } else {
            $projectRoot = dirname(__DIR__, 2);
            $publicFrontController = realpath($projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php');
            $currentScript = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
            $assetsBase = $currentScript !== false && $publicFrontController !== false && $currentScript === $publicFrontController
                ? app_url('/assets')
                : app_url('/public/assets');
        }
    }

    $normalized = trim($path, '/');
    return $normalized === '' ? $assetsBase : $assetsBase . '/' . $normalized;
}

function app_request_path(): string {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $basePath = app_base_path();
    if ($basePath !== '' && str_starts_with($uri, $basePath)) {
        $uri = substr($uri, strlen($basePath)) ?: '/';
    }
    $uri = '/' . ltrim($uri, '/');
    return $uri === '' ? '/' : $uri;
}

function prefix_root_relative_urls(string $html): string {
    $basePath = app_base_path();
    if ($basePath === '') return $html;

    $basePathWithSlash = rtrim($basePath, '/') . '/';

    return preg_replace_callback(
        '/\b(href|src|action)=([\'"])(.*?)\2/i',
        function (array $match) use ($basePath, $basePathWithSlash): string {
            $attribute = $match[1];
            $quote = $match[2];
            $url = $match[3];

            if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
                return $match[0];
            }

            if ($url === $basePath || str_starts_with($url, $basePathWithSlash)) {
                return $match[0];
            }

            return $attribute . '=' . $quote . $basePath . $url . $quote;
        },
        $html
    );
}
