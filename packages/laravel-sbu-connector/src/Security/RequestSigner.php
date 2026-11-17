<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Security;

/**
 * HMAC signature for service-to-service calls.
 * The signed string covers the body and the actor/tenant headers so a captured
 * request cannot be replayed against another tenant or another person.
 */
final class RequestSigner
{
    public function canonical(
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $body,
        string $actorId,
        string $tenantId,
    ): string {
        return $timestamp."\n"
            .$nonce."\n"
            .strtoupper($method)."\n"
            .$path."\n"
            .hash('sha256', $body)."\n"
            .$actorId."\n"
            .$tenantId;
    }

    public function sign(
        string $secret,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $body,
        string $actorId,
        string $tenantId,
    ): string {
        return hash_hmac('sha256', $this->canonical($timestamp, $nonce, $method, $path, $body, $actorId, $tenantId), $secret);
    }

    public function verify(
        string $secret,
        string $provided,
        int $timestamp,
        string $nonce,
        string $method,
        string $path,
        string $body,
        string $actorId,
        string $tenantId,
        int $leewaySeconds,
        ?int $now = null,
    ): bool {
        $now ??= time();
        if ($secret === '' || $provided === '' || $nonce === '' || $actorId === '' || $tenantId === '') {
            return false;
        }
        if (abs($now - $timestamp) > $leewaySeconds) {
            return false;
        }

        $expected = $this->sign($secret, $timestamp, $nonce, $method, $path, $body, $actorId, $tenantId);

        return hash_equals($expected, $provided);
    }
}
