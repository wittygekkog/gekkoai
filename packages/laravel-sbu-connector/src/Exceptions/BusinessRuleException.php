<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Exceptions;

use RuntimeException;

/**
 * A business rule rejected the operation before any write was committed.
 */
final class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
        public readonly string $errorCode = 'business_rule',
    ) {
        parent::__construct($message);
    }
}
