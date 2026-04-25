<?php

namespace App\Data\Zone;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Max;

class ZoneData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name,

        #[StringType]
        public ?string $address,

        #[Required, StringType, Max(100)]
        public string $city,

        #[Required, StringType, Max(100)]
        public string $state
    ) {}
}
