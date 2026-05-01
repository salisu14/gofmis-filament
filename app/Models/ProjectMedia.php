<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMedia extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'project_media';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'type',
        'title',
        'file_path',
        'uploaded_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the project that this media belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who uploaded this media file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Helper to check if the media is a photo.
     */
    public function isPhoto(): bool
    {
        return $this->type === 'photo';
    }

    /**
     * Helper to check if the media is a video.
     */
    public function isVideo(): bool
    {
        return $this->type === 'video';
    }

    /**
     * Helper to check if the media is a document.
     */
    public function isDocument(): bool
    {
        return $this->type === 'document';
    }

    public function zone(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            Zone::class,
            Project::class,
            'id',           // Foreign key on Project
            'id',           // Foreign key on Zone
            'project_id',  // Local key on ProjectMilestone
            'zone_id'       // Local key on Project
        );
    }

    protected static function booted(): void
    {
        static::creating(function ($media) {
            if (! $media->uploaded_by) {
                $media->uploaded_by = auth()->id();
            }
        });
    }
}
