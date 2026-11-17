<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Exceptions;

use RuntimeException;

final class ConnectorTimeoutException extends RuntimeException
{
    public function __construct(public readonly string $operationId)
    {
        parent::__construct('The connector did not confirm the operation before the timeout.');
    }
}
