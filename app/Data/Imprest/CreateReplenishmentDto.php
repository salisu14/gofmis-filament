<?php

namespace App\Data\Imprest;

use Carbon\Carbon;

readonly class CreateReplenishmentDto
{
    public function __construct(
        public string $fundId,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public string $requestedBy,
        public ?string $notes = null,
    ) {}
}
