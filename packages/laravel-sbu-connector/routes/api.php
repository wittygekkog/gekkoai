<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WittyGekko\SbuConnector\Http\ConnectorController;
use WittyGekko\SbuConnector\Middleware\VerifySignedSbuRequest;

Route::get('/api/ai/v1/health', [ConnectorController::class, 'health']);

Route::middleware(VerifySignedSbuRequest::class)->group(function (): void {
    Route::get('/api/ai/v1/capabilities', [ConnectorController::class, 'capabilities']);
    Route::post('/api/ai/v1/operations/validate', [ConnectorController::class, 'validateOperation']);
    Route::post('/api/ai/v1/operations/execute', [ConnectorController::class, 'execute']);
    Route::get('/api/ai/v1/operations/{operationId}', [ConnectorController::class, 'show'])
        ->whereUuid('operationId');
});
