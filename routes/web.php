<?php

use App\Http\Controllers\Admin\AccessController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeployController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\LogicController;
use App\Http\Controllers\Admin\ObjectiveController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth('admin')->check() ? 'admin.dashboard' : 'admin.login');
});

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/admin/login', [LoginController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('admin.login.store');
});

Route::post('/github/deploy', [DeployController::class, 'webhook'])->name('github.deploy.webhook');

Route::middleware(['auth:admin', 'admin.active'])->group(function (): void {
    Route::post('/admin/logout', [LoginController::class, 'destroy'])->name('admin.logout');
    Route::get('/admin/password', [PasswordController::class, 'edit'])->name('admin.password.edit');
    Route::post('/admin/password', [PasswordController::class, 'update'])->name('admin.password.update');
});

Route::middleware(['auth:admin', 'admin.active', 'password.changed'])->group(function (): void {
    Route::get('/admin', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::post('/admin/tenant', [DashboardController::class, 'switchTenant'])->name('admin.tenant.switch');

    Route::get('/admin/workspace', [WorkspaceController::class, 'index'])->name('admin.workspace.index');
    Route::post('/admin/workspace', [WorkspaceController::class, 'start'])->name('admin.workspace.start');
    Route::get('/admin/workspace/{conversation}', [WorkspaceController::class, 'show'])->name('admin.workspace.show');
    Route::post('/admin/workspace/{conversation}/messages', [WorkspaceController::class, 'message'])->middleware('throttle:ai')->name('admin.workspace.message');
    Route::post('/admin/workspace/{conversation}/audio', [WorkspaceController::class, 'audio'])->middleware('throttle:ai')->name('admin.workspace.audio');
    Route::post('/admin/workspace/{conversation}/transcript', [WorkspaceController::class, 'confirmTranscript'])->name('admin.workspace.transcript');

    Route::get('/admin/conversations', [WorkspaceController::class, 'conversations'])->name('admin.conversations.index');
    Route::get('/admin/objective', [ObjectiveController::class, 'index'])->name('admin.objective.index');
    Route::post('/admin/objective/chat', [ObjectiveController::class, 'chat'])->middleware('throttle:ai')->name('admin.objective.chat');
    Route::post('/admin/objective', [ObjectiveController::class, 'store'])->name('admin.objective.store');
    Route::patch('/admin/objective/{objective}', [ObjectiveController::class, 'update'])->name('admin.objective.update');

    Route::get('/admin/logic', [LogicController::class, 'index'])->name('admin.logic.index');
    Route::post('/admin/logic/chat', [LogicController::class, 'chat'])->middleware('throttle:ai')->name('admin.logic.chat');
    Route::post('/admin/logic', [LogicController::class, 'store'])->name('admin.logic.store');
    Route::patch('/admin/logic/{rule}', [LogicController::class, 'update'])->name('admin.logic.update');
    Route::get('/admin/operations', [ReviewController::class, 'operations'])->name('admin.operations.index');
    Route::get('/admin/operations/{operation}', [ReviewController::class, 'showOperation'])->name('admin.operations.show');
    Route::post('/admin/operations/{operation}/reconcile', [ReviewController::class, 'reconcile'])->name('admin.operations.reconcile');
    Route::post('/admin/operations/{operation}/cancel', [ReviewController::class, 'cancel'])->name('admin.operations.cancel');

    Route::get('/admin/approvals', [ReviewController::class, 'approvals'])->name('admin.approvals.index');
    Route::post('/admin/approvals/{approval}/approve', [ReviewController::class, 'approve'])->name('admin.approvals.approve');
    Route::post('/admin/approvals/{approval}/reject', [ReviewController::class, 'reject'])->name('admin.approvals.reject');

    Route::get('/admin/escalations', [ReviewController::class, 'escalations'])->name('admin.escalations.index');
    Route::post('/admin/escalations/{escalation}/resolve', [ReviewController::class, 'resolveEscalation'])->name('admin.escalations.resolve');

    Route::get('/admin/tenants', [TenantController::class, 'index'])->name('admin.tenants.index');
    Route::get('/admin/tenants/create', [TenantController::class, 'create'])->name('admin.tenants.create');
    Route::post('/admin/tenants', [TenantController::class, 'store'])->name('admin.tenants.store');
    Route::get('/admin/tenants/{tenant}', [TenantController::class, 'show'])->name('admin.tenants.show');
    Route::get('/admin/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('admin.tenants.edit');
    Route::patch('/admin/tenants/{tenant}', [TenantController::class, 'update'])->name('admin.tenants.update');

    Route::get('/admin/connectors', [SystemController::class, 'connectors'])->name('admin.connectors.index');
    Route::post('/admin/connectors/{connector}/health', [SystemController::class, 'health'])->name('admin.connectors.health');
    Route::get('/admin/models', [SystemController::class, 'models'])->name('admin.models.index');
    Route::post('/admin/models', [SystemController::class, 'storeModel'])->name('admin.models.store');
    Route::get('/admin/knowledge', [KnowledgeController::class, 'index'])->name('admin.knowledge.index');
    Route::post('/admin/knowledge', [KnowledgeController::class, 'store'])->name('admin.knowledge.store');
    Route::patch('/admin/knowledge/{document}', [KnowledgeController::class, 'update'])->name('admin.knowledge.update');
    Route::delete('/admin/knowledge/{document}', [KnowledgeController::class, 'destroy'])->name('admin.knowledge.destroy');
    Route::get('/admin/knowledge/{document}/file', [KnowledgeController::class, 'file'])->name('admin.knowledge.file');
    Route::post('/admin/knowledge/{document}/approve', [KnowledgeController::class, 'approve'])->name('admin.knowledge.approve');
    Route::get('/admin/learning', [KnowledgeController::class, 'learning'])->name('admin.learning.index');
    Route::post('/admin/feedback', [KnowledgeController::class, 'feedback'])->name('admin.feedback.store');
    Route::post('/admin/learning/{example}/approve', [KnowledgeController::class, 'approveExample'])->name('admin.learning.approve');
    Route::post('/admin/learning/evaluate', [KnowledgeController::class, 'evaluate'])->name('admin.learning.evaluate');
    Route::get('/admin/confidence', [SystemController::class, 'confidence'])->name('admin.confidence.index');
    Route::get('/admin/audit', [SystemController::class, 'audit'])->name('admin.audit.index');

    Route::get('/admin/access', [AccessController::class, 'index'])->name('admin.access.index');
    Route::get('/admin/access/create', [AccessController::class, 'create'])->name('admin.access.create');
    Route::post('/admin/access', [AccessController::class, 'store'])->name('admin.access.store');
    Route::get('/admin/access/{admin}/edit', [AccessController::class, 'edit'])->name('admin.access.edit');
    Route::patch('/admin/access/{admin}', [AccessController::class, 'update'])->name('admin.access.update');
    Route::delete('/admin/access/{admin}', [AccessController::class, 'destroy'])->name('admin.access.destroy');
});

Route::middleware(['auth:admin', 'admin.active', 'password.changed', 'superadmin'])->group(function (): void {
    Route::get('/admin/settings', [SettingController::class, 'index'])->name('admin.settings.index');
    Route::get('/admin/settings/create', [SettingController::class, 'create'])->name('admin.settings.create');
    Route::post('/admin/settings', [SettingController::class, 'store'])->name('admin.settings.store');
    Route::get('/admin/settings/{setting}/edit', [SettingController::class, 'edit'])->name('admin.settings.edit');
    Route::patch('/admin/settings/{setting}', [SettingController::class, 'update'])->name('admin.settings.update');
    Route::delete('/admin/settings/{setting}', [SettingController::class, 'destroy'])->name('admin.settings.destroy');
    Route::get('/github/deploy', [DeployController::class, 'index'])->name('github.deploy');
});
