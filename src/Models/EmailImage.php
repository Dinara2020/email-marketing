<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EmailImage extends Model
{
    protected $table = 'email_images';

    protected $fillable = [
        'tenant_id',
        'filename',
        'original_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = ['url'];

    /**
     * Allowed mime types for email images
     */
    public static array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Max file size in bytes (2MB)
     */
    public static int $maxFileSize = 2097152;

    /**
     * Get the full URL to the image
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Delete the file when model is deleted
     */
    protected static function booted(): void
    {
        static::deleting(function (EmailImage $image) {
            Storage::disk($image->disk)->delete($image->path);
        });
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, ?string $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Get human readable file size
     */
    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
