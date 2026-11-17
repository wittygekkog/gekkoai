<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use WittyGekko\SbuConnector\Security\RequestSigner;

/**
 * Rejects unsigned, expired, or replayed service requests.
 */
final class VerifySignedSbuRequest
{
    public function __construct(private readonly RequestSigner $signer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $keyId = (string) $request->header('X-Sbu-Key', '');
        $clients = config('sbu-connector.clients', []);
        $secret = is_array($clients) ? (string) ($clients[$keyId] ?? '') : '';
        $timestamp = (int) $request->header('X-Sbu-Timestamp', '0');
        $nonce = (string) $request->header('X-Sbu-Nonce', '');
        $signature = (string) $request->header('X-Sbu-Signature', '');
        $actorId = (string) $request->header('X-Sbu-Actor', '');
        $tenantId = (string) $request->header('X-Sbu-Tenant', '');

        $valid = $this->signer->verify(
            $secret,
            $signature,
            $timestamp,
            $nonce,
            $request->getMethod(),
            $request->getPathInfo(),
            $request->getContent(),
            $actorId,
            $tenantId,
            (int) config('sbu-connector.replay_leeway_seconds', 300),
        );

        if (! $valid) {
            return response()->json([
                'success' => false,
                'message' => 'The service signature is invalid or expired.',
                'data' => [],
            ], 401);
        }

        $nonceKey = 'sbu-nonce:'.$keyId.':'.$nonce;
        if (! Cache::add($nonceKey, 1, now()->addSeconds((int) config('sbu-connector.replay_leeway_seconds', 300)))) {
            return response()->json([
                'success' => false,
                'message' => 'This signed request was already used.',
                'data' => [],
            ], 409);
        }

        $request->attributes->set('sbu.key_id', $keyId);
        $request->attributes->set('sbu.actor_id', $actorId);
        $request->attributes->set('sbu.tenant_id', $tenantId);

        return $next($request);
    }
}
