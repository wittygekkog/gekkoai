<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class IllegalTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct('The operation cannot move from '.$from.' to '.$to.'.');
    }
}
