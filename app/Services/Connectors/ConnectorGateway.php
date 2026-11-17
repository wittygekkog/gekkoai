<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\TenantConnector;
use Illuminate\Support\Facades\Http;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Contracts\OperationResult;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;
use WittyGekko\SbuConnector\Exceptions\ConnectorTimeoutException;
use WittyGekko\SbuConnector\Security\RequestSigner;
use WittyGekko\SbuConnector\Services\IdempotencyStore;
use WittyGekko\SbuConnector\Services\OperationDispatcher;

/**
 * Sends an operation to the in-process simulator or to the SBU's registered HTTPS endpoint.
 * The URL is taken from the connector row. The model cannot supply one.
 */
final class ConnectorGateway
{
    public function __construct(
        private readonly OperationDispatcher $dispatcher,
        private readonly IdempotencyStore $idempotency,
        private readonly RequestSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(TenantConnector $connector, string $operation, array $parameters, OperationContext $context, bool $write): OperationResult
    {
        if ($connector->mode !== 'live') {
            $result = $this->dispatcher->execute($operation, $parameters, $context);
            if ($write && config('gekko.simulate_timeout_after_commit')) {
                throw new ConnectorTimeoutException($context->operationId);
            }

            return $result;
        }

        if (! config('gekko.allow_live_connectors')) {
            throw new BusinessRuleException('connector', 'Live connector calls are turned off.');
        }

        return $this->postLive($connector, $operation, $parameters, $context);
    }

    public function idempotency(): IdempotencyStore
    {
        return $this->idempotency;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function postLive(TenantConnector $connector, string $operation, array $parameters, OperationContext $context): OperationResult
    {
        $base = rtrim((string) $connector->base_url, '/');
        $parts = parse_url($base);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new BusinessRuleException('connector', 'A live connector must use an HTTPS address that was saved for this business unit.');
        }

        $credentials = $connector->encrypted_credentials ?? [];
        $secret = (string) ($credentials['secret'] ?? '');
        $path = '/api/ai/v1/operations/execute';
        $body = json_encode([
            'request_id' => $context->requestId,
            'operation_id' => $context->operationId,
            'tenant_id' => $context->tenantId,
            'operation' => $operation,
            'operation_version' => '1.0',
            'parameters' => $parameters,
            'idempotency_key' => $context->idempotencyKey,
            'approval_reference' => $context->approvalReference,
            'correlation_id' => $context->correlationId,
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $signature = $this->signer->sign($secret, $timestamp, $nonce, 'POST', $path, $body, $context->actorId, $context->tenantId);

        try {
            $response = Http::timeout(8)
                ->withBody($body, 'application/json')
                ->withHeaders([
                    'X-Sbu-Key' => (string) $connector->key_id,
                    'X-Sbu-Timestamp' => (string) $timestamp,
                    'X-Sbu-Nonce' => $nonce,
                    'X-Sbu-Signature' => $signature,
                    'X-Sbu-Actor' => $context->actorId,
                    'X-Sbu-Tenant' => $context->tenantId,
                ])
                ->post($base.$path);
        } catch (\Throwable $exception) {
            throw new ConnectorTimeoutException($context->operationId);
        }

        if ($response->serverError() || $response->status() === 0) {
            throw new ConnectorTimeoutException($context->operationId);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new ConnectorTimeoutException($context->operationId);
        }

        return OperationResult::fromArray($payload);
    }
}
