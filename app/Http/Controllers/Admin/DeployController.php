<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Process\Process;

/**
 * GitHub webhook that redeploys the current branch.
 *
 * Cloned from the webpay modern SettingsController::deployGithub flow.
 * The superadmin page only reports how the webhook is wired; it does not deploy.
 *
 * Security notes:
 * - Signature verification is fail-closed: a missing or wrong X-Hub-Signature-256 is rejected.
 * - The webhook secret and deploy token are read from config (so `config:cache` works) and must be present.
 * - Only pushes to refs/heads/main are honored.
 * - Shell commands are executed via Symfony Process with explicit argument arrays
 *   (no shell interpolation, no command injection surface).
 * - The deploy token is passed through an ephemeral fetch URL, never written to .git/config.
 */
class DeployController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.deploy.index', [
            'owner' => (string) config('services.github.deploy_owner', ''),
            'repo' => (string) config('services.github.deploy_repo', ''),
            'secretConfigured' => (string) config('services.github.webhook_secret', '') !== '',
            'tokenConfigured' => (string) config('services.github.deploy_token', '') !== '',
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $secret = (string) config('services.github.webhook_secret', '');
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $payload = $request->getContent();

        if ($secret === '') {
            Log::warning('GitHub deploy refused: webhook secret is not configured.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => 'error', 'message' => 'Webhook not configured'], 503);
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);
        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('GitHub deploy refused: invalid signature.', [
                'ip' => $request->ip(),
                'user-agent' => $request->userAgent(),
                'signature_present' => $signature !== '',
            ]);

            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $branch = (string) ($data['ref'] ?? '');
        $pusher = (string) ($data['pusher']['name'] ?? 'unknown');
        $commit = substr((string) ($data['after'] ?? ''), 0, 7);

        // Local audit relation. Values are already bounded; the raw payload and token are never stored.
        $this->audit->log(null, null, 'deploy.webhook', 'github', null, null, [
            'ref' => mb_substr($branch, 0, 120),
            'pusher' => mb_substr($pusher, 0, 100),
            'commit' => $commit,
            'delivery' => mb_substr((string) $request->header('X-GitHub-Delivery', ''), 0, 80),
        ]);

        if ($branch !== 'refs/heads/main') {
            return response()->json(['status' => 'ignored', 'message' => 'Not main branch']);
        }

        $timestamp = now()->format('Y-m-d H:i:s');

        $token = (string) config('services.github.deploy_token', '');
        if ($token === '') {
            Log::error('GitHub deploy refused: deploy token is not configured.');

            return response()->json(['status' => 'error', 'message' => 'Deploy token not configured'], 503);
        }

        $repoOwner = (string) config('services.github.deploy_owner', 'mansouranos');
        $repoName = (string) config('services.github.deploy_repo', 'gekkoai');
        $repoUrl = sprintf('https://x-access-token:%s@github.com/%s/%s.git', $token, $repoOwner, $repoName);
        $projectPath = base_path();

        // Each command is a plain argv array, executed without a shell.
        // The token is kept out of `git remote set-url` so it never lands in .git/config.
        $commands = [
            ['git', 'fetch', $repoUrl, 'main'],
            ['git', 'reset', '--hard', 'FETCH_HEAD'],
        ];

        foreach ($commands as $cmd) {
            $process = new Process($cmd, $projectPath);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::error('GitHub deploy command failed.', [
                    'command' => $cmd[0].' '.($cmd[1] ?? ''),
                    'exit_code' => $process->getExitCode(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Deployment command failed.',
                    'timestamp' => $timestamp,
                ], 500);
            }
        }

        Log::info('GitHub deploy succeeded.', [
            'pusher' => mb_substr($pusher, 0, 100),
            'branch' => $branch,
            'commit' => $commit,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Deployment completed',
            'timestamp' => $timestamp,
            'commit' => $commit,
        ]);
    }
}
