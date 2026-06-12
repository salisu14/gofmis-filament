<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyInformation extends Model
{
    use HasFactory;

    protected $table = 'company_information';

    const SINGLETON_ID = 1;

    const DEFAULT_COMPANY_NAME = 'Garko Orphans Foundation';
    const DEFAULT_ADDRESS_LINE_1 = 'Shop No.1, Garko Juma\'at Mosque, Garko Local Government, Kano';
    const DEFAULT_COUNTRY_CODE = 'NGA';

    protected $fillable = [
        'company_name',
        'trading_name',
        'registration_no',
        'tax_registration_no',
        'address_line_1',
        'address_line_2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'phone_no',
        'mobile_no',
        'email',
        'website',
        'contact_person_name',
        'contact_person_title',
        'contact_person_phone',
        'contact_person_email',
        'logo_path',
        'favicon_path',
        'bank_name',
        'bank_account_no',
        'bank_branch',
        'swift_code',
        'fiscal_year_start_month',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'fiscal_year_start_month' => 'integer',
    ];

    // ─── Singleton Accessors ───────────────────────────────────────

    public static function instance(): self
    {
        return self::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'id' => self::SINGLETON_ID,
                'company_name' => self::DEFAULT_COMPANY_NAME,
                'address_line_1' => self::DEFAULT_ADDRESS_LINE_1,
                'country_code' => self::DEFAULT_COUNTRY_CODE,
            ]
        );
    }

    public static function setInstance(array $data): self
    {
        $instance = self::instance();
        $instance->update($data);

        return $instance;
    }

    public static function value(string $field, mixed $default = null): mixed
    {
        return self::instance()->getAttribute($field) ?? $default;
    }

    // ─── Helper Methods ────────────────────────────────────────────

    public static function companyName(): string
    {
        return self::value('company_name', self::DEFAULT_COMPANY_NAME);
    }

    public static function tradingName(): string
    {
        return self::value('trading_name') ?: self::companyName();
    }

    public static function fullAddress(): string
    {
        return implode(', ', self::addressLines());
    }

    public static function addressLines(): array
    {
        $instance = self::instance();

        return array_filter([
            $instance->address_line_1,
            $instance->address_line_2,
            trim(implode(', ', array_filter([
                $instance->city,
                trim(implode(' ', array_filter([
                    $instance->state_province,
                    $instance->postal_code,
                ]))),
            ]))),
            self::countryName($instance->country_code),
        ], fn ($line) => filled($line));
    }

    public static function logoUrl(): ?string
    {
        return self::instance()->logo_url;
    }

    public static function faviconUrl(): ?string
    {
        return self::instance()->favicon_url;
    }

    // ─── Accessors ─────────────────────────────────────────────────

    public function getLogoUrlAttribute(): ?string
    {
        return $this->resolveStorageUrl($this->logo_path);
    }

    public function getFaviconUrlAttribute(): ?string
    {
        return $this->resolveStorageUrl($this->favicon_path);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->trading_name ?: $this->company_name;
    }

    public function getFullAddressAttribute(): string
    {
        return implode(', ', static::addressLines());
    }

    // ─── Utilities ─────────────────────────────────────────────────

    private function resolveStorageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : Storage::disk('public')->url($path);
    }

    private static function countryName(?string $countryCode): ?string
    {
        return match (strtoupper((string) $countryCode)) {
            '', 'NGA' => null,
            'NG' => 'Nigeria',
            default => strtoupper((string) $countryCode),
        };
    }
}
