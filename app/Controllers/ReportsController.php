<?php declare(strict_types=1);
namespace App\Controllers;
use App\Core\Controller;
use App\Models\Reports;
class ReportsController extends Controller
{
    public function index(): void
    {
        $from = $_GET["from"] ?? date("Y-m-01");
        $to = $_GET["to"] ?? date("Y-m-d");
        $type = $_GET["type"] ?? "sales";
        $report = new Reports();
        $data = match ($type) {
            "purchases" => $report->purchases($from, $to),
            "expenses" => $report->expenses($from, $to),
            "inventory" => $report->inventoryValued(),
            "movements" => $report->inventoryMovements($from, $to),
            default => $report->sales($from, $to),
        };
        $this->view("reports/index", compact("data", "from", "to", "type"));
    }
    public function journal(): void
    {
        $from = $_GET["from"] ?? date("Y-m-01");
        $to = $_GET["to"] ?? date("Y-m-d");
        $report = new Reports();
        $rows = $report->journal($from, $to);
        $this->view("reports/journal", compact("rows", "from", "to"));
    }
    public function ledger(): void
    {
        $from = $_GET["from"] ?? date("Y-m-01");
        $to = $_GET["to"] ?? date("Y-m-d");
        $report = new Reports();
        $rows = $report->ledger($from, $to);
        $this->view("reports/ledger", compact("rows", "from", "to"));
    }
    public function balanceSheet(): void
    {
        $from = $_GET["from"] ?? date("Y-m-01");
        $to = $_GET["to"] ?? date("Y-m-d");
        $report = new Reports();
        $balance = $report->balanceSheet($from, $to);
        $this->view("reports/balance_sheet", compact("balance", "from", "to"));
    }
}
