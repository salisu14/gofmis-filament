<?php
// app/Models/IdCardTemplate.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdCardTemplate extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'layout_config',
        'background_image_path',
        'is_active'
    ];

    protected $casts = [
        'layout_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class, 'template_id');
    }

    protected static function booted(): void
    {
        static::saving(function (IdCardTemplate $template): void {
            $template->layout_config = array_replace_recursive(
                static::defaultLayoutConfig($template->type),
                $template->layout_config ?? []
            );
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public static function defaultForType(string $type): self
    {
        $template = static::query()
            ->active()
            ->forType($type)
            ->latest('updated_at')
            ->first();

        if (! $template) {
            throw new \RuntimeException("No active ID card template is configured for {$type} cards.");
        }

        return $template;
    }

    public static function defaultLayoutConfig(?string $type = null): array
    {
        $isWidow = $type === 'widow';

        return [
            'primary_color' => $isWidow ? '#8B4513' : '#1E90FF',
            'secondary_color' => $isWidow ? '#FFF8F0' : '#F0F8FF',
            'font_family' => 'Helvetica',
            'photo_width_mm' => '16',
            'photo_height_mm' => '20',
            'qr_size_mm' => '13',
            'header_height_mm' => '13.5',
            'show_background_image' => true,
        ];
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->layout_config ?? [], $key, $default);
    }
}
