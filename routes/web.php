<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SnapshotController;
use Illuminate\Support\Facades\Route;

// ─── Pages ────────────────────────────────────────────────────────────────────
Route::get('/', [AnalyticsController::class, 'dashboard'])->name('dashboard');
Route::get('/input', [AssetController::class, 'index'])->name('input.index');
Route::get('/analysis', [AnalyticsController::class, 'analysis'])->name('analysis.index');
Route::get('/settings', [CategoryController::class, 'index'])->name('settings.index');

// ─── Assets CRUD ──────────────────────────────────────────────────────────────
Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

// ─── Snapshots ────────────────────────────────────────────────────────────────
Route::post('/snapshots', [SnapshotController::class, 'store'])->name('snapshots.store');

// ─── Categories CRUD ──────────────────────────────────────────────────────────
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

// ─── Export (plain HTTP, not Inertia) ─────────────────────────────────────────
Route::get('/export/csv', [AnalyticsController::class, 'exportCsv'])->name('export.csv');
