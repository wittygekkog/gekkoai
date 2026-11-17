<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Contracts;

final readonly class OperationResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        public bool $success,
        public string $status,
        public array $data = [],
        public array $errors = [],
        public ?string $errorCode = null,
        public bool $duplicate = false,
        public ?string $executedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            success: (bool) ($payload['success'] ?? false),
            status: (string) ($payload['status'] ?? 'failed'),
            data: is_array($payload['data'] ?? null) ? $payload['data'] : [],
            errors: is_array($payload['errors'] ?? null) ? array_values($payload['errors']) : [],
            errorCode: isset($payload['error_code']) ? (string) $payload['error_code'] : null,
            duplicate: (bool) ($payload['duplicate'] ?? false),
            executedAt: isset($payload['executed_at']) ? (string) $payload['executed_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'data' => $this->data,
            'errors' => $this->errors,
            'error_code' => $this->errorCode,
            'duplicate' => $this->duplicate,
            'executed_at' => $this->executedAt,
        ];
    }
}
