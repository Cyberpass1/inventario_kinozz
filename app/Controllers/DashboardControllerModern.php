<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\Product;
use App\Services\PdfService;

class DashboardControllerModern extends Controller
{
    public function index(): void
    {
        [
            'stats' => $stats,
            'rate' => $rate,
            'alerts' => $alerts,
            'from' => $from,
            'to' => $to,
            'cashFlow' => $cashFlow,
            'composition' => $composition,
            'topProducts' => $topProducts,
        ] = $this->dashboardPayload();

        $this->view(
            'dashboard/workspace',
            compact('stats', 'rate', 'alerts', 'from', 'to', 'cashFlow', 'composition', 'topProducts'),
            'layouts/app_modern'
        );
    }

    public function pdf(): void
    {
        (new PdfService())->dashboard($this->dashboardPayload());
    }

    private function dashboardPayload(): array
    {
        $from = $this->sanitizeDate($_GET['from'] ?? date('Y-m-d', strtotime('-14 days')));
        $to = $this->sanitizeDate($_GET['to'] ?? date('Y-m-d'));

        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }

        $dashboard = new Dashboard();

        return [
            'stats' => $dashboard->stats(),
            'rate' => [
                'rate' => system_exchange_rate($to),
                'currency_from' => 'USD',
                'currency_to' => 'VES',
            ],
            'alerts' => (new Product())->lowStock(),
            'from' => $from,
            'to' => $to,
            'cashFlow' => $dashboard->cashFlow($from, $to),
            'composition' => $dashboard->composition($from, $to),
            'topProducts' => $dashboard->topProducts($from, $to, 6),
        ];
    }

    private function sanitizeDate(string $value): string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }

        return date('Y-m-d');
    }
}
