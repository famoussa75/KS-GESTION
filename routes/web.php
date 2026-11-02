<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProformaController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SubsidiaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::get('/documents/proforma/{proforma}', [ProformaController::class, 'publicShow'])
    ->middleware(['signed', 'throttle:60,1'])->name('public.proformas.show');
Route::get('/documents/facture/{invoice}', [InvoiceController::class, 'publicShow'])
    ->middleware(['signed', 'throttle:60,1'])->name('public.invoices.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/filiales', [SubsidiaryController::class, 'index'])->name('subsidiaries.index');
        Route::get('/filiales/{subsidiary}/modifier', [SubsidiaryController::class, 'edit'])->name('subsidiaries.edit');
        Route::put('/filiales/{subsidiary}', [SubsidiaryController::class, 'update'])->name('subsidiaries.update');

        Route::get('/parametres', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
        Route::post('/utilisateurs', [UserController::class, 'store'])->name('users.store');
        Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');

        Route::get('/proformas', [ProformaController::class, 'index'])->name('proformas.index');
        Route::get('/proformas/nouvelle', [ProformaController::class, 'create'])->name('proformas.create');
        Route::post('/proformas', [ProformaController::class, 'store'])->name('proformas.store');
        Route::get('/proformas/{proforma}', [ProformaController::class, 'show'])->name('proformas.show');
        Route::get('/proformas/{proforma}/modifier', [ProformaController::class, 'edit'])->name('proformas.edit');
        Route::put('/proformas/{proforma}', [ProformaController::class, 'update'])->name('proformas.update');
        Route::post('/proformas/{proforma}/envoyer', [ProformaController::class, 'sendToClient'])->name('proformas.send');
        Route::post('/proformas/{proforma}/convertir', [ProformaController::class, 'convert'])->name('proformas.convert');

        Route::get('/factures', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/factures/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

        Route::get('/commandes', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/commandes/nouvelle', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/commandes', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/commandes/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/commandes/{order}/annuler', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/commandes/{order}/avancer', [OrderController::class, 'advance'])->name('orders.advance');

        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::post('/produits', [ProductController::class, 'store'])->name('products.store');
        Route::put('/produits/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('/comptabilite', [AccountingController::class, 'index'])->name('accounting.index');
    });

    Route::middleware('role:admin,stockkeeper')->group(function () {
        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('/stock/{product}', [StockController::class, 'show'])->name('stock.show');
        Route::post('/stock/{product}/ajustement', [StockController::class, 'adjust'])->name('stock.adjust');
        Route::post('/stock/{product}/lots', [StockController::class, 'addBatch'])->name('stock.batches.store');
    });

    Route::middleware('role:admin,cashier')->group(function () {
        Route::get('/caisse', [CashController::class, 'index'])->name('cash.index');
        Route::post('/caisse/ouvrir', [CashController::class, 'open'])->name('cash.open');
        Route::get('/caisse/session/{session}', [CashController::class, 'show'])->name('cash.show');
        Route::post('/caisse/session/{session}/mouvement', [CashController::class, 'movement'])->name('cash.movement');
        Route::post('/caisse/session/{session}/cloturer', [CashController::class, 'close'])->name('cash.close');
    });
});
