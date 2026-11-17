<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\PlatformPayload;
use Illuminate\Http\JsonResponse;

/**
 * Public branding used by the login screen before a session exists.
 */
class BrandingController extends Controller
{
    public function __construct(private readonly PlatformPayload $payload) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Branding loaded.',
            'data' => [
                'branding' => $this->payload->branding(),
            ],
        ]);
    }
}
