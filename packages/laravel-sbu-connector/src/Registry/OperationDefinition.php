<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Registry;

use InvalidArgumentException;

final readonly class OperationDefinition
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $outputSchema
     * @param  list<string>  $requiredPermissions
     * @param  list<string>  $requiredFields
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
        public string $handler,
        public string $risk,
        public array $inputSchema,
        public array $outputSchema,
        public array $requiredPermissions,
        public array $requiredFields,
        public bool $approvalRequired,
        public bool $readOnly,
        public string $idempotency = 'required',
    ) {
        if (! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Unknown risk level.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalog(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'risk' => $this->risk,
            'input_schema' => $this->inputSchema,
            'output_schema' => $this->outputSchema,
            'required_permissions' => $this->requiredPermissions,
            'required_fields' => $this->requiredFields,
            'approval_required' => $this->approvalRequired,
            'read_only' => $this->readOnly,
            'idempotency' => $this->idempotency,
        ];
    }
}
