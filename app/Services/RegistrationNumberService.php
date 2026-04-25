<?php

namespace App\Services;

class RegistrationNumberService
{
    public function generateDeceasedRegNo(): string
    {
        $lastId = \App\Models\Deceased::withTrashed()->max('id') ?? 0;
        return 'GOF-D-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
    }

    public function generateWidowRegNo(): string
    {
        $lastId = \App\Models\Widow::withTrashed()->max('id') ?? 0;
        return 'GOF-W-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
    }

    public function generateOrphanRegNo(): string
    {
        $lastId = \App\Models\Orphan::withTrashed()->max('id') ?? 0;
        return 'GOF-O-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT);
    }
}
