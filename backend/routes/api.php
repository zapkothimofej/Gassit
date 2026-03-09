<?php

use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DiscountRuleController;
use App\Http\Controllers\InsuranceOptionController;
use App\Http\Controllers\ParkController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UnitTypeController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\DamageReportController;
use App\Http\Controllers\DunningController;
use App\Http\Controllers\ElectricityMeterController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WaitingListController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\ReferenceItemController;
use App\Http\Controllers\LlmAccessCodeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RevenueTargetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);
    });
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks')->group(function () {
    Route::get('/', [ParkController::class, 'index']);
    Route::post('/', [ParkController::class, 'store']);
    Route::put('/{id}', [ParkController::class, 'update']);
    Route::delete('/{id}', [ParkController::class, 'destroy']);
    Route::post('/{id}/logo', [ParkController::class, 'uploadLogo']);
    Route::get('/{id}/settings', [ParkController::class, 'getSettings']);
    Route::put('/{id}/settings', [ParkController::class, 'updateSettings']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/unit-types')->group(function () {
    Route::get('/', [UnitTypeController::class, 'index']);
    Route::post('/', [UnitTypeController::class, 'store']);
    Route::put('/{id}', [UnitTypeController::class, 'update']);
    Route::delete('/{id}', [UnitTypeController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('unit-types')->group(function () {
    Route::post('/{id}/floor-plan', [UnitTypeController::class, 'uploadFloorPlan']);
    Route::post('/{id}/features', [UnitTypeController::class, 'syncFeatures']);
    Route::get('/{id}/availability', [UnitTypeController::class, 'availability']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager'])->prefix('parks/{parkId}/units')->group(function () {
    Route::get('/', [UnitController::class, 'index']);
    Route::post('/', [UnitController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager'])->prefix('units')->group(function () {
    Route::put('/{id}', [UnitController::class, 'update']);
    Route::delete('/{id}', [UnitController::class, 'destroy']);
    Route::put('/{id}/status', [UnitController::class, 'updateStatus']);
    Route::post('/{id}/photos', [UnitController::class, 'uploadPhoto']);
    Route::delete('/{id}/photos/{photoId}', [UnitController::class, 'deletePhoto']);
    Route::get('/{id}/history', [UnitController::class, 'history']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/discount-rules')->group(function () {
    Route::get('/', [DiscountRuleController::class, 'index']);
    Route::post('/', [DiscountRuleController::class, 'store']);
    Route::put('/{id}', [DiscountRuleController::class, 'update']);
    Route::delete('/{id}', [DiscountRuleController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('parks/{parkId}/insurance-options')->group(function () {
    Route::get('/', [InsuranceOptionController::class, 'index']);
    Route::post('/', [InsuranceOptionController::class, 'store']);
    Route::put('/{id}', [InsuranceOptionController::class, 'update']);
    Route::delete('/{id}', [InsuranceOptionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('unit-types/{id}')->group(function () {
    Route::get('/discount-rules', [DiscountRuleController::class, 'forUnitType']);
    Route::get('/insurance-options', [InsuranceOptionController::class, 'forUnitType']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('customers')->group(function () {
    Route::get('/blacklist', [CustomerController::class, 'blacklistIndex']);
    Route::get('/', [CustomerController::class, 'index']);
    Route::post('/', [CustomerController::class, 'store']);
    Route::put('/{id}', [CustomerController::class, 'update']);
    Route::delete('/{id}', [CustomerController::class, 'destroy']);
    Route::post('/{id}/documents', [CustomerController::class, 'uploadDocument']);
    Route::get('/{id}/documents', [CustomerController::class, 'listDocuments']);
    Route::delete('/{id}/documents/{docId}', [CustomerController::class, 'deleteDocument']);
    Route::post('/{id}/gdpr-delete', [CustomerController::class, 'gdprDelete']);
    Route::post('/{id}/blacklist', [CustomerController::class, 'blacklist']);
    Route::delete('/{id}/blacklist', [CustomerController::class, 'removeBlacklist']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('applications')->group(function () {
    Route::get('/', [ApplicationController::class, 'index']);
    Route::post('/', [ApplicationController::class, 'store']);
    Route::put('/{id}', [ApplicationController::class, 'update']);
    Route::delete('/{id}', [ApplicationController::class, 'destroy']);
    Route::put('/{id}/status', [ApplicationController::class, 'updateStatus']);
    Route::post('/{id}/assign', [ApplicationController::class, 'assign']);
    Route::post('/{id}/credit-check', [ApplicationController::class, 'creditCheck']);
    Route::post('/{id}/waiting-list', [ApplicationController::class, 'moveToWaitingList']);
    Route::post('/{id}/convert', [ApplicationController::class, 'convert']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('parks/{parkId}/waiting-list')->group(function () {
    Route::get('/', [WaitingListController::class, 'index']);
    Route::post('/', [WaitingListController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('waiting-list')->group(function () {
    Route::put('/{id}', [WaitingListController::class, 'update']);
    Route::delete('/{id}', [WaitingListController::class, 'destroy']);
    Route::post('/{id}/notify', [WaitingListController::class, 'notify']);
    Route::post('/{id}/convert', [WaitingListController::class, 'convert']);
});

// Contracts
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('applications')->group(function () {
    Route::post('/{id}/contract', [ContractController::class, 'generateFromApplication']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker'])->prefix('contracts')->group(function () {
    Route::get('/', [ContractController::class, 'index']);
    Route::get('/{id}', [ContractController::class, 'show']);
    Route::put('/{id}', [ContractController::class, 'update']);
    Route::post('/{id}/send-for-signature', [ContractController::class, 'sendForSignature']);
    Route::post('/{id}/activate', [ContractController::class, 'activate']);
    Route::post('/{id}/terminate', [ContractController::class, 'terminate']);
    Route::post('/{id}/renew', [ContractController::class, 'renew']);
});

// E-sign webhook (public — no auth, signed by provider)
Route::post('/webhooks/esign', [ContractController::class, 'esignWebhook']);

// Deposits
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,accountant'])->group(function () {
    Route::get('/deposits', [DepositController::class, 'index']);
    Route::get('/contracts/{id}/deposit', [DepositController::class, 'show']);
    Route::put('/deposits/{id}/received', [DepositController::class, 'markReceived']);
    Route::post('/deposits/{id}/return', [DepositController::class, 'processReturn']);
    Route::post('/deposits/{id}/mollie-payout', [DepositController::class, 'molliePayout']);
});

// Invoices
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,accountant'])->group(function () {
    Route::get('/invoices/datev-export', [InvoiceController::class, 'datevExport']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::post('/invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('/invoices/{id}/send', [InvoiceController::class, 'send']);
    Route::post('/invoices/{id}/cancel', [InvoiceController::class, 'cancel']);
    Route::post('/invoices/{id}/payment-link', [PaymentController::class, 'createPaymentLink']);
});

// Payments
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,accountant'])->group(function () {
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund']);
});

// Mollie webhook (public — verified by Mollie signature in production)
Route::post('/webhooks/mollie', [PaymentController::class, 'mollieWebhook']);

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/parks', [UserController::class, 'syncParks']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});

// Damage reports
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,park_worker'])->prefix('damage-reports')->group(function () {
    Route::get('/', [DamageReportController::class, 'index']);
    Route::post('/', [DamageReportController::class, 'store']);
    Route::put('/{id}', [DamageReportController::class, 'update']);
    Route::delete('/{id}', [DamageReportController::class, 'destroy']);
    Route::post('/{id}/photos', [DamageReportController::class, 'uploadPhoto']);
    Route::put('/{id}/status', [DamageReportController::class, 'updateStatus']);
    Route::post('/{id}/assign-vendor', [DamageReportController::class, 'assignVendor']);
    Route::post('/{id}/invoice', [DamageReportController::class, 'generateInvoice']);
});

Route::middleware(['auth:sanctum', 'role:admin,main_manager,accountant'])->prefix('debtors')->group(function () {
    Route::get('/', [DunningController::class, 'debtors']);
    Route::post('/{customerId}/pause', [DunningController::class, 'pause']);
    Route::post('/{customerId}/escalate', [DunningController::class, 'escalate']);
    Route::post('/{customerId}/resolve', [DunningController::class, 'resolve']);
});

// Electricity meters
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,park_worker'])->group(function () {
    Route::get('/units/{unitId}/meters', [ElectricityMeterController::class, 'index']);
    Route::post('/units/{unitId}/meters', [ElectricityMeterController::class, 'store']);
    Route::put('/meters/{id}', [ElectricityMeterController::class, 'update']);
    Route::delete('/meters/{id}', [ElectricityMeterController::class, 'destroy']);
    Route::post('/meters/{meterId}/readings', [ElectricityMeterController::class, 'storeReading']);
    Route::get('/meters/{meterId}/readings', [ElectricityMeterController::class, 'indexReadings']);
    Route::post('/meters/{meterId}/readings/{readingId}/bill', [ElectricityMeterController::class, 'billReading']);
});

// Electricity pricing
Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->group(function () {
    Route::get('/parks/{parkId}/electricity-pricing', [ElectricityMeterController::class, 'pricingIndex']);
    Route::post('/parks/{parkId}/electricity-pricing', [ElectricityMeterController::class, 'pricingStore']);
});

// Vendors
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager'])->prefix('vendors')->group(function () {
    Route::get('/', [VendorController::class, 'index']);
    Route::post('/', [VendorController::class, 'store']);
    Route::get('/{id}', [VendorController::class, 'show']);
    Route::put('/{id}', [VendorController::class, 'update']);
    Route::delete('/{id}', [VendorController::class, 'destroy']);
    Route::get('/{id}/invoices', [VendorController::class, 'invoicesIndex']);
    Route::post('/{id}/invoices', [VendorController::class, 'invoicesStore']);
    Route::put('/{id}/invoices/{invoiceId}', [VendorController::class, 'invoicesUpdate']);
    Route::post('/{id}/invoices/{invoiceId}/pay', [VendorController::class, 'invoicesPay']);
    Route::get('/{id}/damage-reports', [VendorController::class, 'damageReports']);
});

// Tasks
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,park_worker,office_worker'])->prefix('tasks')->group(function () {
    Route::get('/dashboard', [TaskController::class, 'dashboard']);
    Route::get('/calendar', [TaskController::class, 'calendar']);
    Route::get('/', [TaskController::class, 'index']);
    Route::post('/', [TaskController::class, 'store']);
    Route::put('/{id}', [TaskController::class, 'update']);
    Route::delete('/{id}', [TaskController::class, 'destroy']);
    Route::put('/{id}/status', [TaskController::class, 'updateStatus']);
    Route::post('/{id}/assign', [TaskController::class, 'assign']);
});

// Mail templates
Route::middleware(['auth:sanctum', 'role:admin,main_manager,office_worker'])->prefix('mail-templates')->group(function () {
    Route::get('/', [MailController::class, 'templatesIndex']);
    Route::post('/', [MailController::class, 'templatesStore']);
    Route::put('/{id}', [MailController::class, 'templatesUpdate']);
    Route::delete('/{id}', [MailController::class, 'templatesDestroy']);
});

// Mail send/preview/log
Route::middleware(['auth:sanctum', 'role:admin,main_manager,office_worker'])->prefix('mail')->group(function () {
    Route::post('/preview', [MailController::class, 'preview']);
    Route::post('/send', [MailController::class, 'send']);
    Route::post('/mass-send', [MailController::class, 'massSend']);
    Route::post('/schedule', [MailController::class, 'schedule']);
    Route::get('/sent', [MailController::class, 'sent']);
});

// Document templates
Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->prefix('document-templates')->group(function () {
    Route::get('/', [DocumentTemplateController::class, 'index']);
    Route::post('/', [DocumentTemplateController::class, 'store']);
    Route::put('/{id}', [DocumentTemplateController::class, 'update']);
    Route::post('/{id}/clone', [DocumentTemplateController::class, 'clone']);
});

// System settings
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('system-settings')->group(function () {
    Route::get('/', [SystemSettingController::class, 'index']);
    Route::put('/', [SystemSettingController::class, 'update']);
    Route::get('/{key}', [SystemSettingController::class, 'show']);
});

// Reference items
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,office_worker,customer_service'])->prefix('reference-items')->group(function () {
    Route::get('/', [ReferenceItemController::class, 'index']);
});
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('reference-items')->group(function () {
    Route::post('/', [ReferenceItemController::class, 'store']);
    Route::put('/{id}', [ReferenceItemController::class, 'update']);
    Route::delete('/{id}', [ReferenceItemController::class, 'destroy']);
});

// LLM access codes
Route::middleware(['auth:sanctum', 'role:admin,main_manager'])->group(function () {
    Route::get('/parks/{parkId}/access-codes', [LlmAccessCodeController::class, 'index']);
    Route::post('/parks/{parkId}/access-codes', [LlmAccessCodeController::class, 'store']);
    Route::put('/parks/{parkId}/access-codes/{id}', [LlmAccessCodeController::class, 'update']);
    Route::delete('/parks/{parkId}/access-codes/{id}', [LlmAccessCodeController::class, 'destroy']);
    Route::post('/parks/{parkId}/access-codes/sync', [LlmAccessCodeController::class, 'sync']);
});

// Notifications
Route::middleware(['auth:sanctum'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markRead']);
});

// Global search
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/search', [SearchController::class, 'search']);
});

// Dashboard
Route::middleware(['auth:sanctum'])->prefix('dashboard')->group(function () {
    Route::get('/kpis', [DashboardController::class, 'kpis']);
    Route::get('/mahnstuffe', [DashboardController::class, 'mahnstuffe']);
    Route::get('/revenue', [DashboardController::class, 'revenue']);
});

// Reports
Route::middleware(['auth:sanctum', 'role:admin,main_manager,rental_manager,accountant,office_worker,customer_service'])->prefix('reports')->group(function () {
    Route::get('/applications', [ReportController::class, 'applications']);
    Route::get('/customers', [ReportController::class, 'customers']);
    Route::get('/units', [ReportController::class, 'units']);
    Route::get('/finance', [ReportController::class, 'finance']);
});
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('reports')->group(function () {
    Route::get('/audit', [ReportController::class, 'audit']);
});

// Audit logs (admin only)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('audit-logs')->group(function () {
    Route::get('/', [AuditLogController::class, 'index']);
    Route::get('/export', [AuditLogController::class, 'export']);
    Route::get('/{id}', [AuditLogController::class, 'show']);
});

// Revenue targets
Route::middleware(['auth:sanctum', 'role:admin,main_manager,accountant'])->group(function () {
    Route::get('/parks/{parkId}/revenue-targets', [RevenueTargetController::class, 'index']);
    Route::post('/parks/{parkId}/revenue-targets', [RevenueTargetController::class, 'store']);
    Route::put('/revenue-targets/{id}', [RevenueTargetController::class, 'update']);
    Route::get('/parks/{parkId}/revenue-targets/{year}/{month}/actual', [RevenueTargetController::class, 'actual']);
});
