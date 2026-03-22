<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\AnalysisController;
use App\Http\Controllers\Analytics\DashboardController;
use App\Http\Controllers\Analytics\ExportCsvController;
use App\Http\Controllers\Assets\BulkMoveController;
use App\Http\Controllers\Assets\CopyFromMonthController;
use App\Http\Controllers\Assets\DestroyController as DestroyAssetController;
use App\Http\Controllers\Assets\IndexController as IndexAssetController;
use App\Http\Controllers\Assets\StoreController as StoreAssetController;
use App\Http\Controllers\Assets\UpdateController as UpdateAssetController;
use App\Http\Controllers\Categories\DestroyController as DestroyCategoryController;
use App\Http\Controllers\Categories\IndexController as IndexCategoryController;
use App\Http\Controllers\Categories\StoreController as StoreCategoryController;
use App\Http\Controllers\Categories\UpdateController as UpdateCategoryController;
use App\Http\Controllers\Prices\RefreshController as RefreshPriceController;
use App\Http\Controllers\Snapshots\StoreController as StoreSnapshotController;
use Illuminate\Support\Facades\Route;

// ─── Pages ────────────────────────────────────────────────────────────────────
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/input', IndexAssetController::class)->name('input.index');
Route::get('/analysis', AnalysisController::class)->name('analysis.index');
Route::get('/settings', IndexCategoryController::class)->name('settings.index');

// ─── Assets CRUD ──────────────────────────────────────────────────────────────
Route::prefix('assets')->name('assets.')->group(function () {
    Route::post('/', StoreAssetController::class)->name('store');
    Route::put('/{asset}', UpdateAssetController::class)->name('update');
    Route::delete('/{asset}', DestroyAssetController::class)->name('destroy');
    Route::post('/bulk-move', BulkMoveController::class)->name('bulk-move');
    Route::post('/copy-from-month', CopyFromMonthController::class)->name('copy-from-month');
});

// ─── Snapshots ────────────────────────────────────────────────────────────────
Route::post('/snapshots', StoreSnapshotController::class)->name('snapshots.store');

// ─── Categories CRUD ──────────────────────────────────────────────────────────
Route::prefix('categories')->name('categories.')->group(function () {
    Route::post('/', StoreCategoryController::class)->name('store');
    Route::put('/{category}', UpdateCategoryController::class)->name('update');
    Route::delete('/{category}', DestroyCategoryController::class)->name('destroy');
});

// ─── Prices ───────────────────────────────────────────────────────────────────
Route::post('/prices/refresh', RefreshPriceController::class)->name('prices.refresh');

// ─── Export (plain HTTP, not Inertia) ─────────────────────────────────────────
Route::get('/export/csv', ExportCsvController::class)->name('export.csv');
