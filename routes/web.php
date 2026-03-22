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
Route::post('/assets/bulk-move', BulkMoveController::class)->name('assets.bulk-move');
Route::post('/assets/copy-from-month', CopyFromMonthController::class)->name('assets.copy-from-month');
Route::post('/assets', StoreAssetController::class)->name('assets.store');
Route::put('/assets/{asset}', UpdateAssetController::class)->name('assets.update');
Route::delete('/assets/{asset}', DestroyAssetController::class)->name('assets.destroy');

// ─── Snapshots ────────────────────────────────────────────────────────────────
Route::post('/snapshots', StoreSnapshotController::class)->name('snapshots.store');

// ─── Categories CRUD ──────────────────────────────────────────────────────────
Route::post('/categories', StoreCategoryController::class)->name('categories.store');
Route::put('/categories/{category}', UpdateCategoryController::class)->name('categories.update');
Route::delete('/categories/{category}', DestroyCategoryController::class)->name('categories.destroy');

// ─── Prices ───────────────────────────────────────────────────────────────────
Route::post('/prices/refresh', RefreshPriceController::class)->name('prices.refresh');

// ─── Export (plain HTTP, not Inertia) ─────────────────────────────────────────
Route::get('/export/csv', ExportCsvController::class)->name('export.csv');
