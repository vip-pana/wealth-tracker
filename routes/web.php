<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalysisController;
use App\Http\Controllers\Analytics\CsvTemplateController;
use App\Http\Controllers\Analytics\DashboardController;
use App\Http\Controllers\Analytics\ExportCsvController;
use App\Http\Controllers\Analytics\ImportCsvController;
use App\Http\Controllers\Assets\CopyFromMonthController;
use App\Http\Controllers\Assets\DestroyController as DestroyAssetController;
use App\Http\Controllers\Assets\IndexController as IndexAssetController;
use App\Http\Controllers\Assets\RestoreController as RestoreAssetController;
use App\Http\Controllers\Assets\StoreController as StoreAssetController;
use App\Http\Controllers\Assets\UpdateController as UpdateAssetController;
use App\Http\Controllers\Backup\StoreController as StoreBackupController;
use App\Http\Controllers\Banking\CallbackController as BankingCallbackController;
use App\Http\Controllers\Banking\ConnectController as BankingConnectController;
use App\Http\Controllers\Banking\DisconnectController as BankingDisconnectController;
use App\Http\Controllers\Banking\LinkAccountController as BankingLinkAccountController;
use App\Http\Controllers\Categories\DestroyController as DestroyCategoryController;
use App\Http\Controllers\Categories\IndexController as IndexCategoryController;
use App\Http\Controllers\Categories\RestoreController as RestoreCategoryController;
use App\Http\Controllers\Categories\StoreController as StoreCategoryController;
use App\Http\Controllers\Categories\UpdateController as UpdateCategoryController;
use App\Http\Controllers\Goals\DestroyController as DestroyGoalController;
use App\Http\Controllers\Goals\IndexController as IndexGoalController;
use App\Http\Controllers\Goals\RestoreController as RestoreGoalController;
use App\Http\Controllers\Goals\StoreController as StoreGoalController;
use App\Http\Controllers\Goals\UpdateController as UpdateGoalController;
use App\Http\Controllers\Pension\DestroyController as DestroyPensionController;
use App\Http\Controllers\Pension\IndexController as IndexPensionController;
use App\Http\Controllers\Pension\StoreController as StorePensionController;
use App\Http\Controllers\Pension\UpdateController as UpdatePensionController;
use App\Http\Controllers\Prices\RefreshController as RefreshPriceController;
use App\Http\Controllers\Snapshots\StoreController as StoreSnapshotController;
use Illuminate\Support\Facades\Route;

// ─── Pages ────────────────────────────────────────────────────────────────────
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/input', IndexAssetController::class)->name('input.index');
Route::get('/analysis', AnalysisController::class)->name('analysis.index');
Route::get('/settings', IndexCategoryController::class)->name('settings.index');
Route::get('/goal', IndexGoalController::class)->name('goal.index');
Route::get('/pension', IndexPensionController::class)->name('pension.index');

// ─── Assets CRUD ──────────────────────────────────────────────────────────────
Route::prefix('assets')->name('assets.')->group(function () {
    Route::post('/', StoreAssetController::class)->name('store');
    Route::put('/{asset}', UpdateAssetController::class)->name('update');
    Route::delete('/{asset}', DestroyAssetController::class)->name('destroy');
    Route::post('/{asset}/restore', RestoreAssetController::class)->name('restore');
    Route::post('/copy-from-month', CopyFromMonthController::class)->name('copy-from-month');
});

// ─── Snapshots ────────────────────────────────────────────────────────────────
Route::post('/snapshots', StoreSnapshotController::class)->name('snapshots.store');

// ─── Categories CRUD ──────────────────────────────────────────────────────────
Route::prefix('categories')->name('categories.')->group(function () {
    Route::post('/', StoreCategoryController::class)->name('store');
    Route::put('/{category}', UpdateCategoryController::class)->name('update');
    Route::delete('/{category}', DestroyCategoryController::class)->name('destroy');
    Route::post('/{category}/restore', RestoreCategoryController::class)->name('restore');
});

// ─── Goal ─────────────────────────────────────────────────────────────────────
Route::prefix('goal')->name('goal.')->group(function () {
    Route::post('/', StoreGoalController::class)->name('store');
    Route::put('/{goal}', UpdateGoalController::class)->name('update');
    Route::delete('/{goal}', DestroyGoalController::class)->name('destroy');
    Route::post('/{goal}/restore', RestoreGoalController::class)->name('restore');
});

// ─── Pension ──────────────────────────────────────────────────────────────────
Route::prefix('pension')->name('pension.')->group(function () {
    Route::post('/', StorePensionController::class)->name('store');
    Route::put('/{asset}', UpdatePensionController::class)->name('update');
    Route::delete('/{asset}', DestroyPensionController::class)->name('destroy');
});

// ─── Backup ───────────────────────────────────────────────────────────────────
Route::post('/backup', StoreBackupController::class)->name('backup.store');

// ─── Prices ───────────────────────────────────────────────────────────────────
Route::post('/prices/refresh', RefreshPriceController::class)->name('prices.refresh');

// ─── Banking (Enable Banking open banking, read-only) ─────────────────────────
Route::prefix('banking')->name('banking.')->group(function () {
    Route::post('/connect', BankingConnectController::class)->name('connect');
    Route::get('/callback', BankingCallbackController::class)->name('callback');
    Route::post('/accounts/{account}/link', BankingLinkAccountController::class)->name('accounts.link');
    Route::delete('/connections/{connection}', BankingDisconnectController::class)->name('connections.disconnect');
});

// ─── Export / Import (plain HTTP, not Inertia) ────────────────────────────────
Route::get('/export/csv', ExportCsvController::class)->name('export.csv');
Route::get('/import/csv/template', CsvTemplateController::class)->name('import.csv.template');
Route::post('/import/csv', ImportCsvController::class)->name('import.csv');
