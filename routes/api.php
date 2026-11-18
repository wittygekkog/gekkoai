<?php

use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\MobileAuthController;
use App\Http\Controllers\Api\V1\PlatformApiController;
use Illuminate\Support\Facades\Route;

/*
| Mobile contract for the GekkoAi Android app.
| Paths are relative to the /api prefix, so the public base is /api/v1/.
| These routes do not use the browser session or CSRF.
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/branding', [BrandingController::class, 'show'])->name('api.v1.branding');
    Route::post('/auth/login', [MobileAuthController::class, 'login'])->middleware('throttle:login')->name('api.v1.login');
    Route::post('/auth/refresh', [MobileAuthController::class, 'refresh'])->middleware('throttle:login')->name('api.v1.refresh');

    Route::middleware('auth.api')->group(function (): void {
        Route::post('/auth/logout', [MobileAuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('/profile', [MobileAuthController::class, 'profile'])->name('api.v1.profile');

        Route::get('/tenants', [PlatformApiController::class, 'tenants'])->name('api.v1.tenants');
        Route::get('/conversations', [PlatformApiController::class, 'conversations'])->name('api.v1.conversations');
        Route::post('/conversations', [PlatformApiController::class, 'storeConversation'])->name('api.v1.conversations.store');
        Route::get('/conversations/{conversation}', [PlatformApiController::class, 'showConversation'])->name('api.v1.conversations.show');
        Route::get('/conversations/{conversation}/logic', [PlatformApiController::class, 'logic'])->name('api.v1.conversations.logic');

        Route::middleware('throttle:ai')->group(function (): void {
            Route::post('/conversations/{conversation}/messages', [PlatformApiController::class, 'message'])->name('api.v1.conversations.message');
            Route::post('/conversations/{conversation}/audio', [PlatformApiController::class, 'audio'])->name('api.v1.conversations.audio');
        });

        Route::get('/operations', [PlatformApiController::class, 'operations'])->name('api.v1.operations');
        Route::get('/operations/{operation}', [PlatformApiController::class, 'showOperation'])->name('api.v1.operations.show');
        Route::get('/approvals', [PlatformApiController::class, 'approvals'])->name('api.v1.approvals');
        Route::post('/approvals/{approval}/approve', [PlatformApiController::class, 'approve'])->name('api.v1.approvals.approve');
        Route::post('/approvals/{approval}/reject', [PlatformApiController::class, 'reject'])->name('api.v1.approvals.reject');
        Route::get('/escalations', [PlatformApiController::class, 'escalations'])->name('api.v1.escalations');
        Route::post('/escalations/{escalation}/resolve', [PlatformApiController::class, 'resolveEscalation'])->name('api.v1.escalations.resolve');
        Route::get('/rules', [PlatformApiController::class, 'rules'])->name('api.v1.rules');
        Route::post('/rules', [PlatformApiController::class, 'storeRule'])->name('api.v1.rules.store');
        Route::patch('/rules/{rule}', [PlatformApiController::class, 'updateRule'])->name('api.v1.rules.update');
        Route::get('/documents', [PlatformApiController::class, 'documents'])->name('api.v1.documents');
        Route::post('/documents', [PlatformApiController::class, 'storeDocument'])->name('api.v1.documents.store');
        Route::get('/documents/{document}', [PlatformApiController::class, 'showDocument'])->name('api.v1.documents.show');
        Route::patch('/documents/{document}', [PlatformApiController::class, 'updateDocument'])->name('api.v1.documents.update');
        Route::post('/documents/{document}/approve', [PlatformApiController::class, 'approveDocument'])->name('api.v1.documents.approve');
        Route::delete('/documents/{document}', [PlatformApiController::class, 'deleteDocument'])->name('api.v1.documents.delete');
        Route::get('/flags', [PlatformApiController::class, 'flags'])->name('api.v1.flags');
        Route::get('/access', [PlatformApiController::class, 'access'])->name('api.v1.access');
        Route::post('/feedback', [PlatformApiController::class, 'feedback'])->name('api.v1.feedback');
    });
});
