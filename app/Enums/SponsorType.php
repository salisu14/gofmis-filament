<?php

namespace App\Enums;

enum SponsorType: string
{
    case Individual = 'individual';
    case Corporate = 'corporate';
    case NGO = 'ngo';
    case Organization = 'organization';
    case Government = 'government';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Corporate => 'Corporate/Company',
            self::NGO => 'NGO',
            self::Organization => 'Organization',
            self::Government => 'Government',
            self::Other => 'Other',
        };
    }
}
