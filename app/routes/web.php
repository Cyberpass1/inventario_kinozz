<?php
declare(strict_types=1);

use App\Controllers\AuthControllerCompat as AuthController;
use App\Controllers\ClientControllerModern as ClientController;
use App\Controllers\DashboardControllerModern as DashboardController;
use App\Controllers\DeliveryNoteControllerModern as DeliveryNoteController;
use App\Controllers\ExpenseController;
use App\Controllers\InventoryControllerModern as InventoryController;
use App\Controllers\InvoiceControllerModern as InvoiceController;
use App\Controllers\ProductionControllerModern as ProductionController;
use App\Controllers\PurchaseControllerModern as PurchaseController;
use App\Controllers\RateController;
use App\Controllers\ReportsControllerModern as ReportsController;
use App\Controllers\ServiceControllerModern as ServiceController;
use App\Controllers\SettingsController;
use App\Controllers\SupplierControllerModern as SupplierController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

$router = new Router();
$router->get('/', [AuthController::class, 'loginForm']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

$auth = [AuthMiddleware::class];
$admin = [AuthMiddleware::class, RoleMiddleware::class . ':administrator'];
$ops = [AuthMiddleware::class, RoleMiddleware::class . ':administrator,vendor'];
$readOnly = [AuthMiddleware::class, RoleMiddleware::class . ':administrator,vendor,general_consultant'];

$router->get('/dashboard', [DashboardController::class, 'index'], $auth);
$router->get('/dashboard/pdf', [DashboardController::class, 'pdf'], $auth);
$router->get('/rates/by-date', [RateController::class, 'byDate'], $auth);

$router->get('/inventory', [InventoryController::class, 'index'], $readOnly);
$router->post('/inventory/categories', [InventoryController::class, 'storeCategory'], $admin);
$router->post('/inventory/categories/{id}', [InventoryController::class, 'updateCategory'], $admin);
$router->post('/inventory/categories/{id}/delete', [InventoryController::class, 'deleteCategory'], $admin);
$router->post('/inventory/products', [InventoryController::class, 'storeProduct'], $admin);
$router->post('/inventory/products/duplicate', [InventoryController::class, 'duplicateProduct'], $admin);
$router->post('/inventory/products/variants', [InventoryController::class, 'createVariants'], $admin);
$router->post('/inventory/products/{id}', [InventoryController::class, 'updateProduct'], $admin);
$router->post('/inventory/products/{id}/status', [InventoryController::class, 'toggleStatus'], $admin);
$router->post('/inventory/products/{id}/delete', [InventoryController::class, 'softDelete'], $admin);
$router->get('/inventory/movements', [InventoryController::class, 'movements'], $readOnly);
$router->post('/inventory/adjust', [InventoryController::class, 'adjust'], $admin);
$router->post('/inventory/adjust-bulk', [InventoryController::class, 'adjustBulk'], $admin);

$router->get('/services', [ServiceController::class, 'index'], $readOnly);
$router->post('/services', [ServiceController::class, 'store'], $admin);
$router->post('/services/{id}', [ServiceController::class, 'update'], $admin);
$router->post('/services/{id}/status', [ServiceController::class, 'toggleStatus'], $admin);

$router->get('/production', [ProductionController::class, 'index'], $readOnly);
$router->post('/production', [ProductionController::class, 'store'], $admin);
$router->post('/production/recipes/{id}', [ProductionController::class, 'saveRecipe'], $admin);
$router->post('/production/cancel/{id}', [ProductionController::class, 'cancel'], $admin);

$router->get('/clients', [ClientController::class, 'index'], $readOnly);
$router->get('/clients/search', [ClientController::class, 'search'], $readOnly);
$router->post('/clients', [ClientController::class, 'store'], $ops);
$router->post('/clients/{id}', [ClientController::class, 'update'], $ops);

$router->get('/purchases', [PurchaseController::class, 'index'], $admin);
$router->get('/purchases/export', [PurchaseController::class, 'exportHistory'], $admin);
$router->post('/purchases/suppliers', [PurchaseController::class, 'storeSupplier'], $admin);
$router->post('/purchases', [PurchaseController::class, 'store'], $admin);
$router->get('/purchases/edit-modal/{id}', [PurchaseController::class, 'editModal'], $admin);
$router->post('/purchases/{id}', [PurchaseController::class, 'update'], $admin);
$router->post('/purchases/payments/{id}', [PurchaseController::class, 'registerPayment'], $admin);
$router->post('/purchases/cancel/{id}', [PurchaseController::class, 'cancel'], $admin);
$router->post('/purchases/delete/{id}', [PurchaseController::class, 'delete'], $admin);
$router->get('/purchases/print/{id}', [PurchaseController::class, 'print'], $admin);
$router->get('/purchases/pdf/{id}', [PurchaseController::class, 'pdf'], $admin);

$router->get('/expenses', [ExpenseController::class, 'index'], $readOnly);
$router->post('/expenses/categories', [ExpenseController::class, 'storeCategory'], $admin);
$router->post('/expenses/categories/{id}', [ExpenseController::class, 'updateCategory'], $admin);
$router->post('/expenses/categories/{id}/delete', [ExpenseController::class, 'deleteCategory'], $admin);
$router->post('/expenses', [ExpenseController::class, 'store'], $admin);
$router->post('/expenses/{id}', [ExpenseController::class, 'update'], $admin);
$router->post('/expenses/cancel/{id}', [ExpenseController::class, 'cancel'], $admin);

$router->get('/suppliers', [SupplierController::class, 'index'], $admin);
$router->post('/suppliers', [SupplierController::class, 'store'], $admin);
$router->post('/suppliers/{id}', [SupplierController::class, 'update'], $admin);
$router->post('/suppliers/{id}/status', [SupplierController::class, 'toggleStatus'], $admin);

$router->get('/invoices', [InvoiceController::class, 'index'], $readOnly);
$router->get('/invoices/export', [InvoiceController::class, 'exportHistory'], $readOnly);
$router->post('/invoices/clients', [InvoiceController::class, 'storeClient'], $ops);
$router->post('/invoices', [InvoiceController::class, 'store'], $ops);
$router->get('/invoices/details/{id}', [InvoiceController::class, 'details'], $readOnly);
$router->post('/invoices/payments/{id}', [InvoiceController::class, 'registerPayment'], $ops);
$router->post('/invoices/cancel/{id}', [InvoiceController::class, 'cancel'], $admin);
$router->get('/invoices/print/{id}', [InvoiceController::class, 'print'], $readOnly);
$router->get('/invoices/pdf/{id}', [InvoiceController::class, 'pdf'], $readOnly);

$router->get('/delivery-notes', [DeliveryNoteController::class, 'index'], $readOnly);
$router->get('/delivery-notes/export', [DeliveryNoteController::class, 'exportHistory'], $readOnly);
$router->post('/delivery-notes', [DeliveryNoteController::class, 'store'], $ops);
$router->get('/delivery-notes/details/{id}', [DeliveryNoteController::class, 'details'], $readOnly);
$router->post('/delivery-notes/payments/{id}', [DeliveryNoteController::class, 'registerPayment'], $ops);
$router->post('/delivery-notes/cancel/{id}', [DeliveryNoteController::class, 'cancel'], $admin);
$router->get('/delivery-notes/print/{id}', [DeliveryNoteController::class, 'print'], $readOnly);
$router->get('/delivery-notes/pdf/{id}', [DeliveryNoteController::class, 'pdf'], $readOnly);

$router->get('/reports', [ReportsController::class, 'index'], $readOnly);
$router->get('/reports/pdf', [ReportsController::class, 'pdf'], $readOnly);
$router->get('/reports/inventory-charts/pdf', [ReportsController::class, 'inventoryChartsPdf'], $readOnly);
$router->post('/reports/treasury/opening-balances', [ReportsController::class, 'saveOpeningBalances'], $admin);
$router->post('/reports/treasury/adjustments', [ReportsController::class, 'saveTreasuryAdjustment'], $admin);
$router->post('/reports/treasury/adjustments/{id}/reverse', [ReportsController::class, 'reverseTreasuryAdjustment'], $admin);
$router->get('/reports/journal', [ReportsController::class, 'journal'], $readOnly);
$router->get('/reports/journal/pdf', [ReportsController::class, 'journalPdf'], $readOnly);
$router->get('/reports/ledger', [ReportsController::class, 'ledger'], $readOnly);
$router->get('/reports/ledger/pdf', [ReportsController::class, 'ledgerPdf'], $readOnly);
$router->get('/reports/balance-sheet', [ReportsController::class, 'balanceSheet'], $readOnly);
$router->get('/reports/balance-sheet/pdf', [ReportsController::class, 'balanceSheetPdf'], $readOnly);

$router->get('/profile', [SettingsController::class, 'profile'], $auth);
$router->post('/profile', [SettingsController::class, 'updateProfile'], $auth);

$router->get('/settings', [SettingsController::class, 'index'], $admin);
$router->get('/settings/users', [SettingsController::class, 'users'], $admin);
$router->post('/settings', [SettingsController::class, 'save'], $admin);
$router->post('/settings/rates/sync', [SettingsController::class, 'syncRate'], $admin);
$router->post('/settings/users', [SettingsController::class, 'storeUser'], $admin);
$router->post('/settings/users/{id}', [SettingsController::class, 'updateUser'], $admin);
$router->post('/settings/users/{id}/status', [SettingsController::class, 'toggleUserStatus'], $admin);

return $router;
