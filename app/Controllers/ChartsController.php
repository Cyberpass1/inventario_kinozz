<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Charts;
use App\Services\PdfService;

class ChartsController extends Controller
{
    public function index(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-d', strtotime('first day of this month')));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));

        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $granularity = $this->sanitizeGranularity((string) ($_GET['granularity'] ?? ''));
        $charts = new Charts();
        if ($granularity === 'auto') {
            $granularity = $charts->suggestedGranularity($from, $to);
        }

        $salesByPeriod = $charts->salesByPeriod($from, $to, $granularity);
        $compareFlows = $charts->compareFlows($from, $to, $granularity);
        $topProducts = $charts->topProducts($from, $to, 10);
        $abc = $charts->abcAnalysis($from, $to);
        $topClients = $charts->topClients($from, $to, 10);
        $aging = $charts->receivablesAging($to);
        $paymentMethods = $charts->salesByPaymentMethod($from, $to);
        $forecast = $charts->salesForecast(12, 3);

        $this->view(
            'charts/index',
            compact('from', 'to', 'granularity', 'salesByPeriod', 'compareFlows', 'topProducts', 'abc', 'topClients', 'aging', 'paymentMethods', 'forecast'),
            'layouts/app_modern'
        );
    }

    public function pdf(): void
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-d', strtotime('first day of this month')));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));

        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $granularity = $this->sanitizeGranularity((string) ($_GET['granularity'] ?? ''));
        $charts = new Charts();
        if ($granularity === 'auto') {
            $granularity = $charts->suggestedGranularity($from, $to);
        }

        $payload = [
            'from' => $from,
            'to' => $to,
            'granularity' => $granularity,
            'exchange_rate' => system_exchange_rate($to),
            'salesByPeriod' => $charts->salesByPeriod($from, $to, $granularity),
            'compareFlows' => $charts->compareFlows($from, $to, $granularity),
            'topProducts' => $charts->topProducts($from, $to, 10),
            'abc' => $charts->abcAnalysis($from, $to),
            'topClients' => $charts->topClients($from, $to, 10),
            'aging' => $charts->receivablesAging($to),
            'paymentMethods' => $charts->salesByPaymentMethod($from, $to),
            'forecast' => $charts->salesForecast(12, 3),
        ];

        (new PdfService())->chartsReport($payload);
    }

    private function sanitizeDate(string $value): string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
        return date('Y-m-d');
    }

    private function sanitizeGranularity(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['day', 'week', 'month'], true) ? $value : 'auto';
    }
}
