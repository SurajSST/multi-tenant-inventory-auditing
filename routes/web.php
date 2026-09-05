<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\TenantController;
use App\Livewire;
use Illuminate\Support\Facades\Route;

// ── signing in ────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // ── which school ──────────────────────────────────────────
    // Most people hold one posting and never see the chooser: they are put
    // straight into their school. It matters for whoever works at two.
    Route::get('school', [TenantController::class, 'choose'])->name('tenant.choose');
    Route::post('school', [TenantController::class, 'switch'])->name('tenant.switch');
});

// ── the system ────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', Livewire\Dashboard::class)->name('dashboard');

    // Stock
    Route::get('register', Livewire\Inventory\Register::class)->name('inventory.register');
    Route::get('register/{itemType}/units', Livewire\Inventory\UnitCodes::class)->name('inventory.unit-codes');
    Route::get('register/{itemType}/history', Livewire\Inventory\History::class)->name('inventory.history');
    Route::get('variance', Livewire\Inventory\Variance::class)->name('inventory.variance');
    Route::get('count', Livewire\Inventory\CountSheet::class)
        ->middleware('role:AUDITOR,SUPER_ADMIN')->name('inventory.count');

    // Demand forms and approvals
    Route::get('demands', Livewire\Demands\Index::class)->name('demands.index');
    Route::get('demands/new', Livewire\Demands\Create::class)
        ->middleware('role:INITIATOR,SUPER_ADMIN')->name('demands.create');
    Route::get('demands/queue', Livewire\Demands\Queue::class)->name('demands.queue');
    Route::get('demands/{demand}', Livewire\Demands\Show::class)->name('demands.show');
    Route::get('demands/{demand}/print', [DocumentController::class, 'demand'])->name('demands.print');
    Route::get('demands/{demand}/pdf', [DocumentController::class, 'demand'])->name('demands.pdf');

    // Orders and receipts
    Route::get('orders', Livewire\Orders\Index::class)->name('orders.index');
    Route::get('orders/new', Livewire\Orders\Create::class)
        ->middleware('role:PURCHASE_OFFICER,SUPER_ADMIN')->name('orders.create');
    Route::get('orders/{order}/receive', Livewire\Orders\Receive::class)
        ->middleware('role:RECEIVING_OFFICER,SUPER_ADMIN')->name('orders.receive');
    Route::get('orders/{order}', Livewire\Orders\Show::class)->name('orders.show');
    Route::get('orders/{order}/print', [DocumentController::class, 'order'])->name('orders.print');
    Route::get('orders/{order}/pdf', [DocumentController::class, 'order'])->name('orders.pdf');

    // Bills
    Route::middleware('role:ACCOUNTS,SUPER_ADMIN')->group(function () {
        Route::get('bills', Livewire\Bills\Index::class)->name('bills.index');
        Route::get('bills/new', Livewire\Bills\Create::class)->name('bills.create');
        Route::get('bills/print', [DocumentController::class, 'bills'])->name('bills.print');
        Route::get('bills/pdf', [DocumentController::class, 'bills'])->name('bills.pdf');

        Route::get('petty-cash', Livewire\PettyCash\Index::class)->name('petty-cash.index');
        Route::get('petty-cash/new', Livewire\PettyCash\Issue::class)->name('petty-cash.issue');
        Route::get('petty-cash/{token}', Livewire\PettyCash\Show::class)->name('petty-cash.show');
        Route::get('petty-cash/{token}/print', [DocumentController::class, 'pettyCash'])->name('petty-cash.print');
        Route::get('petty-cash/{token}/pdf', [DocumentController::class, 'pettyCash'])->name('petty-cash.pdf');
    });

    // Setup
    Route::middleware('role:SUPER_ADMIN')->prefix('setup')->name('setup.')->group(function () {
        Route::get('/', Livewire\Setup\Index::class)->name('index');
        Route::get('categories', Livewire\Setup\Categories::class)->name('categories');
        Route::get('locations', Livewire\Setup\Locations::class)->name('locations');
        Route::get('items', Livewire\Setup\ItemTypes::class)->name('items');
        Route::get('approval-ladder', Livewire\Setup\ApprovalLadder::class)->name('ladder');
        Route::get('staff', Livewire\Setup\Staff::class)->name('staff');
        Route::get('settings', Livewire\Setup\Settings::class)->name('settings');
    });

    // ── the platform console ──────────────────────────────────
    // Above every school and outside all of them, which is why this sits on
    // its own middleware rather than on the role: middleware, which only ever
    // answers questions about a posting at one school.
    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::get('/', Livewire\Platform\Schools::class)->name('schools');
        Route::post('exit', [TenantController::class, 'exitToPlatform'])->name('exit');
    });

    Route::get('audit-trail', Livewire\AuditTrail\Index::class)->name('audit.index');

    // Excel exports
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('stock-register', [ExportController::class, 'stockRegister'])->name('stock-register');
        Route::get('unit-list', [ExportController::class, 'unitList'])->name('unit-list');
        Route::get('procurement', [ExportController::class, 'procurement'])->name('procurement');
        Route::get('petty-cash', [ExportController::class, 'pettyCash'])->name('petty-cash');
        Route::get('audit-trail', [ExportController::class, 'auditTrail'])->name('audit-trail');
    });

    // Scanned bills and delivery photos, off a private disk behind a signed URL.
    Route::get('attachments/{path}', AttachmentController::class)
        ->where('path', '.*')->name('attachments.show');
});
