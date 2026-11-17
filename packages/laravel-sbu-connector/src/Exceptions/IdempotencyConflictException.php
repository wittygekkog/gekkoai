<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
}
