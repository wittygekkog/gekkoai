<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WittyGekko\SbuConnector\Contracts\OperationContext;
use WittyGekko\SbuConnector\Exceptions\BusinessRuleException;
use WittyGekko\SbuConnector\Exceptions\IdempotencyConflictException;
use WittyGekko\SbuConnector\Exceptions\UnknownOperationException;
use WittyGekko\SbuConnector\Registry\OperationRegistry;
use WittyGekko\SbuConnector\Services\IdempotencyStore;
use WittyGekko\SbuConnector\Services\OperationDispatcher;

/**
 * Versioned connector API. The signed tenant header is the only tenant id used.
 */
final class ConnectorController
{
    public function health(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Connector is available.',
            'data' => [
                'status' => 'ok',
                'connector_version' => config('sbu-connector.version'),
            ],
        ]);
    }

    public function capabilities(OperationRegistry $registry): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Registered operations.',
            'data' => [
                'connector_version' => config('sbu-connector.version'),
                'operations' => $registry->catalog(),
            ],
        ]);
    }

    public function validateOperation(Request $request, OperationDispatcher $dispatcher): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $normalized = $dispatcher->validate($payload['operation'], $payload['parameters']);

            return response()->json([
                'success' => true,
                'message' => 'Parameters are valid. No business data was changed.',
                'data' => ['parameters' => $normalized],
            ]);
        } catch (UnknownOperationException $exception) {
            return $this->fail($exception->getMessage(), 404);
        } catch (ValidationException $exception) {
            return $this->fail('The parameters are not valid.', 422, $exception->errors());
        }
    }

    public function execute(Request $request, OperationDispatcher $dispatcher): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $context = new OperationContext(
                requestId: $payload['request_id'],
                operationId: $payload['operation_id'],
                tenantId: (string) $request->attributes->get('sbu.tenant_id'),
                idempotencyKey: $payload['idempotency_key'],
                actorId: (string) $request->attributes->get('sbu.actor_id'),
                approvalReference: $payload['approval_reference'],
                correlationId: $payload['correlation_id'],
            );
            $result = $dispatcher->execute($payload['operation'], $payload['parameters'], $context);
            $body = $result->toArray();
            $body['operation_id'] = $payload['operation_id'];

            return response()->json($body, $result->success ? 200 : 422);
        } catch (UnknownOperationException $exception) {
            return $this->fail($exception->getMessage(), 404);
        } catch (ValidationException $exception) {
            return $this->fail('The parameters are not valid.', 422, $exception->errors());
        } catch (BusinessRuleException $exception) {
            return $this->fail($exception->getMessage(), 409, [[
                'field' => $exception->field,
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ]]);
        } catch (IdempotencyConflictException $exception) {
            return $this->fail($exception->getMessage(), 409);
        }
    }

    public function show(Request $request, string $operationId, IdempotencyStore $store): JsonResponse
    {
        $tenantId = (string) $request->attributes->get('sbu.tenant_id');
        $record = $store->findByOperationId($tenantId, $operationId);
        if ($record === null) {
            return $this->fail('Operation was not found for this tenant.', 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Operation status.',
            'data' => [
                'operation_id' => $record->operation_id,
                'operation' => $record->operation_name,
                'status' => $record->status,
                'result' => $record->result,
            ],
        ]);
    }

    /**
     * @return array{
     *     request_id: string,
     *     operation_id: string,
     *     operation: string,
     *     parameters: array<string, mixed>,
     *     idempotency_key: string,
     *     approval_reference: ?string,
     *     correlation_id: string
     * }
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'operation_id' => ['required', 'uuid'],
            'tenant_id' => ['required', 'uuid'],
            'operation' => ['required', 'string', 'max:120'],
            'operation_version' => ['nullable', 'string', 'max:20'],
            'parameters' => ['required', 'array'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'approval_reference' => ['nullable', 'string', 'max:64'],
            'correlation_id' => ['required', 'uuid'],
        ]);

        $signedTenant = (string) $request->attributes->get('sbu.tenant_id');
        if (! hash_equals($signedTenant, (string) $data['tenant_id'])) {
            abort(403, 'The signed tenant does not match the request body.');
        }

        return [
            'request_id' => (string) $data['request_id'],
            'operation_id' => (string) $data['operation_id'],
            'operation' => (string) $data['operation'],
            'parameters' => $data['parameters'],
            'idempotency_key' => (string) $data['idempotency_key'],
            'approval_reference' => isset($data['approval_reference']) ? (string) $data['approval_reference'] : null,
            'correlation_id' => (string) $data['correlation_id'],
        ];
    }

    /**
     * @param  array<mixed>  $errors
     */
    private function fail(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'status' => 'failed',
            'errors' => $errors,
            'data' => [],
        ], $status);
    }
}
