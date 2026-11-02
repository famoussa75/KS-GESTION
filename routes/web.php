<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SubsidiaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::get('/documents/devis/{sale}', [SaleController::class, 'publicShow'])
    ->middleware('signed')->name('public.proformas.show');
Route::get('/documents/facture/{invoice}', [InvoiceController::class, 'publicShow'])
    ->middleware('signed')->name('public.invoices.show');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:admin,cashier,stockkeeper')->group(function () {
        Route::get('/alertes', [AlertController::class, 'index'])->name('alerts.index');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/filiales', [SubsidiaryController::class, 'index'])->name('subsidiaries.index');
        Route::get('/filiales/nouvelle', [SubsidiaryController::class, 'create'])->name('subsidiaries.create');
        Route::post('/filiales', [SubsidiaryController::class, 'store'])->name('subsidiaries.store');
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

        Route::get('/ventes', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/ventes/nouvelle', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/ventes', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/ventes/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/ventes/{sale}/modifier', [SaleController::class, 'edit'])->name('sales.edit');
        Route::put('/ventes/{sale}', [SaleController::class, 'update'])->name('sales.update');
        Route::post('/ventes/{sale}/avancer', [SaleController::class, 'advance'])->name('sales.advance');
        Route::post('/ventes/{sale}/envoyer', [SaleController::class, 'sendToClient'])->name('sales.send');
        Route::post('/ventes/{sale}/convertir', [SaleController::class, 'convert'])->name('sales.convert');

        Route::get('/factures', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/factures/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/factures/{invoice}/encaisser', [InvoiceController::class, 'pay'])->name('invoices.pay');
    });
});
