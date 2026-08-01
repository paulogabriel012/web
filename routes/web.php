<?php

use App\Http\Controllers\Billing\BillingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('billing/plan', [BillingController::class, 'index'])->name('billing.plan');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('billing/status', [BillingController::class, 'status'])->name('billing.status');
    Route::get('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::get('billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});

Route::middleware(['auth', 'verified', 'subscribed'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
