<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Contracts;

/**
 * A business operation exposed to the AI platform.
 * validate() must not write. execute() calls the host application's own services.
 */
interface SbuOperationHandler
{
    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed> Normalized parameters.
     */
    public function validate(array $parameters): array;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters, OperationContext $context): OperationResult;
}
