<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Exceptions;

use RuntimeException;

final class UnknownOperationException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct('Operation ['.$name.'] is not registered.');
    }
}
