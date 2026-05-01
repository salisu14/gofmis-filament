<?php
// app/Enums/ProjectType.php

namespace App\Enums;

enum ProjectType: string
{
    case HOUSE = 'house';
    case SCHOOL = 'school';
    case MOSQUE = 'mosque';
    case CLINIC = 'clinic';
    case WATER = 'water';
    case ROAD = 'road';
    case SKILL_CENTER = 'skill_center';
    case ORPHANAGE = 'orphanage';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::HOUSE => 'Family House',
            self::SCHOOL => 'School Building',
            self::OTHER => 'Other',
            self::MOSQUE => 'Mosque',
            self::CLINIC => 'Health Clinic',
            self::WATER => 'Water Project',
            self::ROAD => 'Road/Infrastructure',
            self::SKILL_CENTER => 'Vocational Center',
            self::ORPHANAGE => 'Orphanage',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::HOUSE => 'heroicon-m-home',
            self::SCHOOL => 'heroicon-m-academic-cap',
            self::OTHER, self::SKILL_CENTER => 'heroicon-m-wrench',
            self::MOSQUE => 'heroicon-m-building-library',
            self::CLINIC => 'heroicon-m-heart',
            self::WATER => 'heroicon-m-beaker',
            self::ROAD => 'heroicon-m-map',
            self::ORPHANAGE => 'heroicon-m-users',
        };
    }
}
