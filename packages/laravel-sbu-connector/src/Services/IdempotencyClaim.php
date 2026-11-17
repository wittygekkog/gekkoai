<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Services;

use WittyGekko\SbuConnector\Models\IdempotencyRecord;

final readonly class IdempotencyClaim
{
    public function __construct(
        public IdempotencyRecord $record,
        public bool $replay,
    ) {}
}
