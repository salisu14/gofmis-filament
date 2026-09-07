<?php

namespace App\Data\Address;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class StateData extends Data
{
    public function __construct(
        #[Required, StringType, Max(255)]
        public string $name
    ) {}
}
