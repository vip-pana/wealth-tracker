<?php

declare(strict_types=1);

use App\Http\Controllers\Advisor\DestroyController as AdvisorDestroyController;
use App\Http\Controllers\Advisor\GenerateController as AdvisorGenerateController;
use App\Http\Controllers\Advisor\IndexController as AdvisorIndexController;
use App\Http\Controllers\Advisor\MessageController as AdvisorMessageController;
use App\Http\Controllers\Advisor\ProposeController as AdvisorProposeController;
use App\Http\Controllers\Advisor\RenameController as AdvisorRenameController;
use App\Http\Controllers\Advisor\RetryMessageController as AdvisorRetryMessageController;
use App\Http\Controllers\Advisor\StartChatController as AdvisorStartChatController;
use App\Http\Controllers\Advisor\StatusController as AdvisorStatusController;
use App\Http\Controllers\Advisor\StoreGoalCompositionController as AdvisorStoreGoalCompositionController;
use App\Http\Controllers\Advisor\StoreGoalCoreController as AdvisorStoreGoalCoreController;
use App\Http\Controllers\Advisor\StoreGoalMilestonesController as AdvisorStoreGoalMilestonesController;
use App\Http\Controllers\Advisor\StoreProfileController as AdvisorStoreProfileController;
use App\Http\Controllers\Analytics\CsvTemplateController;
use App\Http\Controllers\Analytics\DashboardController;
use App\Http\Controllers\Analytics\ExportCsvController;
use App\Http\Controllers\Analytics\ImportCsvController;
use App\Http\Controllers\Assets\CopyFromMonthController;
use App\Http\Controllers\Assets\DestroyController as DestroyAssetController;
use App\Http\Controllers\Assets\IndexController as IndexAssetController;
use App\Http\Controllers\Assets\RestoreController as RestoreAssetController;
use App\Http\Controllers\Assets\StoreController as StoreAssetController;
use App\Http\Controllers\Assets\TransactionsController as TransactionsAssetController;
use App\Http\Controllers\Assets\UnlinkTransactionsController as UnlinkTransactionsAssetController;
use App\Http\Controllers\Assets\UpdateController as UpdateAssetController;
use App\Http\Controllers\Backup\StoreController as StoreBackupController;
use App\Http\Controllers\Banking\CallbackController as BankingCallbackController;
use App\Http\Controllers\Banking\ConnectController as BankingConnectController;
use App\Http\Controllers\Banking\DisconnectController as BankingDisconnectController;
use App\Http\Controllers\Banking\LinkAccountController as BankingLinkAccountController;
use App\Http\Controllers\Cashflow\IndexController as CashflowIndexController;
use App\Http\Controllers\Cashflow\SyncController as CashflowSyncController;
use App\Http\Controllers\Cashflow\UpdateController as CashflowUpdateController;
use App\Http\Controllers\Cashflow\UpdateEmergencyFundController;
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
use App\Http\Controllers\Notifications\ReadAllController as NotificationReadAllController;
use App\Http\Controllers\Notifications\ReadController as NotificationReadController;
use App\Http\Controllers\Pension\DestroyController as DestroyPensionController;
use App\Http\Controllers\Pension\IndexController as IndexPensionController;
use App\Http\Controllers\Pension\StoreController as StorePensionController;
use App\Http\Controllers\Pension\UpdateController as UpdatePensionController;
use App\Http\Controllers\Prices\RefreshController as RefreshPriceController;
use App\Http\Controllers\Scalable\CancelLoginController as CancelLoginScalableController;
use App\Http\Controllers\Scalable\LoginStatusController as LoginStatusScalableController;
use App\Http\Controllers\Scalable\LogoutController as LogoutScalableController;
use App\Http\Controllers\Scalable\RefreshController as RefreshScalableController;
use App\Http\Controllers\Scalable\StartLoginController as StartLoginScalableController;
use App\Http\Controllers\Snapshots\StoreController as StoreSnapshotController;
use Illuminate\Support\Facades\Route;

// ─── Pages ────────────────────────────────────────────────────────────────────
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('/input', IndexAssetController::class)->name('input.index');
Route::get('/advisor', AdvisorIndexController::class)->name('advisor.index');
Route::post('/advisor/generate', AdvisorGenerateController::class)->name('advisor.generate');
Route::post('/advisor/chat', AdvisorStartChatController::class)->name('advisor.chat');
Route::post('/advisor/profile', AdvisorStoreProfileController::class)->name('advisor.profile.store');
Route::post('/advisor/goal', AdvisorStoreGoalCoreController::class)->name('advisor.goal.store');
Route::post('/advisor/goal/milestones', AdvisorStoreGoalMilestonesController::class)->name('advisor.goal.milestones.store');
Route::post('/advisor/goal/composition', AdvisorStoreGoalCompositionController::class)->name('advisor.goal.composition.store');
// Session-scoped routes — the {session} wildcard goes last so it can't shadow
// the static segments above.
Route::get('/advisor/{session}', AdvisorIndexController::class)->name('advisor.show');
Route::get('/advisor/{session}/status', AdvisorStatusController::class)->name('advisor.status');
Route::post('/advisor/{session}/message', AdvisorMessageController::class)->name('advisor.message');
Route::post('/advisor/{session}/propose/{kind}', AdvisorProposeController::class)->name('advisor.propose');
Route::post('/advisor/{session}/message/{message}/retry', AdvisorRetryMessageController::class)->name('advisor.message.retry');
Route::patch('/advisor/{session}', AdvisorRenameController::class)->name('advisor.rename');
Route::delete('/advisor/{session}', AdvisorDestroyController::class)->name('advisor.destroy');
Route::get('/settings', IndexCategoryController::class)->name('settings.index');
Route::get('/goal', IndexGoalController::class)->name('goal.index');
Route::get('/pension', IndexPensionController::class)->name('pension.index');
Route::get('/cashflow', CashflowIndexController::class)->name('cashflow.index');
Route::patch('/cashflow', CashflowUpdateController::class)->name('cashflow.update');
Route::post('/cashflow/sync', CashflowSyncController::class)->name('cashflow.sync');
Route::patch('/cashflow/emergency-fund', UpdateEmergencyFundController::class)->name('cashflow.emergency-fund.update');

// ─── Assets CRUD ──────────────────────────────────────────────────────────────
Route::prefix('assets')->name('assets.')->group(function () {
    Route::post('/', StoreAssetController::class)->name('store');
    Route::put('/{asset}', UpdateAssetController::class)->name('update');
    Route::delete('/{asset}', DestroyAssetController::class)->name('destroy');
    Route::post('/{asset}/restore', RestoreAssetController::class)->name('restore');
    Route::get('/{asset}/transactions', TransactionsAssetController::class)->name('transactions');
    Route::delete('/{asset}/transactions', UnlinkTransactionsAssetController::class)->name('transactions.unlink');
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

// ─── Notifications ────────────────────────────────────────────────────────────
Route::post('/notifications/read-all', NotificationReadAllController::class)->name('notifications.read-all');
Route::post('/notifications/{notification}/read', NotificationReadController::class)->name('notifications.read');

// ─── Backup ───────────────────────────────────────────────────────────────────
Route::post('/backup', StoreBackupController::class)->name('backup.store');

// ─── Prices ───────────────────────────────────────────────────────────────────
Route::post('/prices/refresh', RefreshPriceController::class)->name('prices.refresh');

// ─── Scalable (broker sync via the official CLI) ──────────────────────────────
Route::post('/scalable/refresh', RefreshScalableController::class)->name('scalable.refresh');
Route::post('/scalable/cli/login', StartLoginScalableController::class)->name('scalable.cli.login');
Route::get('/scalable/cli/login/status', LoginStatusScalableController::class)->name('scalable.cli.login.status');
Route::post('/scalable/cli/login/cancel', CancelLoginScalableController::class)->name('scalable.cli.login.cancel');
Route::post('/scalable/cli/logout', LogoutScalableController::class)->name('scalable.cli.logout');

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
