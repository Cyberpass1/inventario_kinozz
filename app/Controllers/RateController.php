<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\BcvRateService;

class RateController extends Controller
{
    public function byDate(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $date = trim((string) ($_GET['date'] ?? ''));
        $mode = trim((string) ($_GET['mode'] ?? ''));
        $customCurrency = trim((string) ($_GET['custom_currency'] ?? 'USD'));
        $customRate = (float) ($_GET['custom_rate'] ?? 0);
        $forceRefresh = in_array(strtolower(trim((string) ($_GET['force_refresh'] ?? '0'))), ['1', 'true', 'yes'], true);
        $service = new BcvRateService();

        try {
            $resolved = $mode !== ''
                ? $service->preview($mode, $customCurrency, $customRate, $date, $forceRefresh)
                : $service->resolve($date, $forceRefresh);
            $payload = [
                'date' => $date !== '' ? $date : (string) ($resolved['date'] ?? ''),
                'rate' => (float) ($resolved['rate'] ?? default_exchange_rate()),
                'rate_date' => (string) ($resolved['date'] ?? ''),
                'currency_from' => (string) ($resolved['currency_from'] ?? base_currency()),
                'currency_to' => (string) ($resolved['currency_to'] ?? secondary_currency()),
                'source' => (string) ($resolved['source'] ?? 'Sistema'),
            ];
        } catch (\Throwable $exception) {
            $payload = [
                'date' => $date !== '' ? $date : date('Y-m-d'),
                'rate' => default_exchange_rate(),
                'rate_date' => date('Y-m-d'),
                'currency_from' => $mode === 'bcv_eur' ? 'EUR' : base_currency(),
                'currency_to' => secondary_currency(),
                'source' => 'Respaldo del sistema',
                'error' => $exception->getMessage(),
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

