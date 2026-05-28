<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Dashboard;
class DashboardController extends Controller
{
    public function index(): void
    {
        $stats = (new Dashboard())->stats();
        $rate = ['rate' => system_exchange_rate(date('Y-m-d')), 'currency_from' => 'USD', 'currency_to' => 'VES'];
        $this->view("dashboard/index", compact("stats", "rate"));
    }
}
