<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signed deployment webhook. Execution stays off unless an operator enables it,
 * and this build never runs git reset.
 */
class DeployController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.deploy.index', [
            'enabled' => (bool) env('GITHUB_DEPLOY_ENABLED', false),
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) env('GITHUB_WEBHOOK_SECRET', '');
        if ($secret === '') {
            return response()->json(['success' => false, 'message' => 'Webhook secret is not configured.', 'data' => []], 403);
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $payload = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature.', 'data' => []], 403);
        }

        $this->audit->log(null, null, 'deploy.webhook', 'github', null, null, [
            'ref' => $request->input('ref'),
            'delivery' => $request->header('X-GitHub-Delivery'),
        ]);

        if (! env('GITHUB_DEPLOY_ENABLED', false)) {
            return response()->json([
                'success' => false,
                'message' => 'The signature is valid. Deployment execution is disabled.',
                'data' => [],
            ], 423);
        }

        return response()->json([
            'success' => false,
            'message' => 'Automatic git reset is not available on this platform. Deploy from the documented release process.',
            'data' => [],
        ], 423);
    }
}
