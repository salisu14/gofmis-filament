<?php
// app/Enums/IllnessCategory.php

namespace App\Enums;

enum IllnessCategory: string
{
    case Infectious = 'infectious';
    case Chronic = 'chronic';
    case Respiratory = 'respiratory';
    case Gastrointestinal = 'gastrointestinal';
    case Hematological = 'hematological';
    case Musculoskeletal = 'musculoskeletal';
    case Allergic = 'allergic';
    case Ophthalmological = 'ophthalmological';
    case Ent = 'ENT';
    case Dental = 'dental';
    case Pediatric = 'pediatric';
    case Parasitic = 'parasitic';
    case Dermatological = 'dermatological';
    case Neurological = 'neurological';
    case Cardiovascular = 'cardiovascular';
    case Endocrine = 'endocrine';
    case Genitourinary = 'genitourinary';
    case ObstetricGynecological = 'obstetric_gynecological';
    case Psychiatric = 'psychiatric';
    case Trauma = 'trauma';
    case Nutritional = 'nutritional';
    case Oncological = 'oncological';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Infectious => 'Infectious Disease',
            self::Chronic => 'Chronic Condition',
            self::Respiratory => 'Respiratory',
            self::Gastrointestinal => 'Gastrointestinal',
            self::Hematological => 'Hematological',
            self::Musculoskeletal => 'Musculoskeletal',
            self::Allergic => 'Allergic',
            self::Ophthalmological => 'Ophthalmological',
            self::Ent => 'ENT (Ear, Nose, Throat)',
            self::Dental => 'Dental',
            self::Pediatric => 'Pediatric',
            self::Parasitic => 'Parasitic',
            self::Dermatological => 'Dermatological',
            self::Neurological => 'Neurological',
            self::Cardiovascular => 'Cardiovascular',
            self::Endocrine => 'Endocrine',
            self::Genitourinary => 'Genitourinary',
            self::ObstetricGynecological => 'Obstetric / Gynecological',
            self::Psychiatric => 'Psychiatric / Mental Health',
            self::Trauma => 'Trauma / Injury',
            self::Nutritional => 'Nutritional',
            self::Oncological => 'Oncological',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Infectious => 'danger',
            self::Chronic => 'warning',
            self::Respiratory => 'info',
            self::Gastrointestinal => 'warning',
            self::Hematological => 'danger',
            self::Musculoskeletal => 'primary',
            self::Allergic => 'success',
            self::Ophthalmological => 'info',
            self::Ent => 'info',
            self::Dental => 'primary',
            self::Pediatric => 'success',
            self::Parasitic => 'danger',
            self::Dermatological => 'warning',
            self::Neurological => 'danger',
            self::Cardiovascular => 'danger',
            self::Endocrine => 'warning',
            self::Genitourinary => 'primary',
            self::ObstetricGynecological => 'warning',
            self::Psychiatric => 'secondary',
            self::Trauma => 'danger',
            self::Nutritional => 'warning',
            self::Oncological => 'danger',
            self::Other => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Infectious => 'heroicon-o-bug-ant',
            self::Chronic => 'heroicon-o-clock',
            self::Respiratory => 'heroicon-o-bolt',
            self::Gastrointestinal => 'heroicon-o-beaker',
            self::Hematological => 'heroicon-o-heart',
            self::Musculoskeletal => 'heroicon-o-bone', // or user-group
            self::Allergic => 'heroicon-o-face-frown',
            self::Ophthalmological => 'heroicon-o-eye',
            self::Ent => 'heroicon-o-ear',
            self::Dental => 'heroicon-o-face-smile',
            self::Pediatric => 'heroicon-o-baby',
            self::Parasitic => 'heroicon-o-bug-ant',
            self::Dermatological => 'heroicon-o-hand-raised',
            self::Neurological => 'heroicon-o-bolt', // brain icon not available
            self::Cardiovascular => 'heroicon-o-heart',
            self::Endocrine => 'heroicon-o-cube',
            self::Genitourinary => 'heroicon-o-beaker',
            self::ObstetricGynecological => 'heroicon-o-user-group',
            self::Psychiatric => 'heroicon-o-chat-bubble-left-right',
            self::Trauma => 'heroicon-o-shield-exclamation',
            self::Nutritional => 'heroicon-o-cake',
            self::Oncological => 'heroicon-o-exclamation-triangle',
            self::Other => 'heroicon-o-question-mark-circle',
        };
    }
}
