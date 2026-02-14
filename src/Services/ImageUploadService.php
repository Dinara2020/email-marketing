<?php

namespace Dinara\EmailMarketing\Services;

use Dinara\EmailMarketing\Models\EmailImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    protected ?string $tenantId = null;

    /**
     * Set tenant ID for multi-tenant support
     */
    public function setTenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;
        return $this;
    }

    /**
     * Get tenant ID from config resolver or manual set
     */
    protected function getTenantId(): ?string
    {
        $resolver = config('email-marketing.tenant_resolver');

        if ($resolver && is_callable($resolver)) {
            return $resolver();
        }

        return $this->tenantId;
    }

    /**
     * Upload an image with security validation
     */
    public function upload(UploadedFile $file): array
    {
        // Validate file
        $validation = $this->validateFile($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => $validation['message'],
            ];
        }

        try {
            // Generate secure filename
            $filename = $this->generateSecureFilename($file);

            // Determine storage path
            $path = $this->getStoragePath($filename);

            // Store file
            $disk = config('email-marketing.image_disk', 'public');
            Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()));

            // Create database record
            $image = EmailImage::create([
                'tenant_id' => $this->getTenantId(),
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
                'uploaded_by' => auth()->id(),
            ]);

            return [
                'success' => true,
                'image' => $image,
                'url' => $image->url,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Validate uploaded file
     */
    protected function validateFile(UploadedFile $file): array
    {
        // Check if file is valid
        if (!$file->isValid()) {
            return [
                'valid' => false,
                'message' => 'Invalid file upload',
            ];
        }

        // Check file size
        if ($file->getSize() > EmailImage::$maxFileSize) {
            $maxMb = EmailImage::$maxFileSize / 1024 / 1024;
            return [
                'valid' => false,
                'message' => "File too large. Maximum size: {$maxMb}MB",
            ];
        }

        // Check mime type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, EmailImage::$allowedMimeTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP',
            ];
        }

        // Verify it's actually an image (not just renamed)
        if (!$this->isRealImage($file)) {
            return [
                'valid' => false,
                'message' => 'File is not a valid image',
            ];
        }

        // Check for malicious content
        if ($this->containsMaliciousContent($file)) {
            return [
                'valid' => false,
                'message' => 'File contains suspicious content',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Verify file is a real image using getimagesize
     */
    protected function isRealImage(UploadedFile $file): bool
    {
        $imageInfo = @getimagesize($file->getRealPath());
        return $imageInfo !== false && $imageInfo[0] > 0 && $imageInfo[1] > 0;
    }

    /**
     * Check for malicious content in file
     */
    protected function containsMaliciousContent(UploadedFile $file): bool
    {
        $content = file_get_contents($file->getRealPath());

        // Check for PHP code
        if (preg_match('/<\?php|<\?=|<\?[^x]/i', $content)) {
            return true;
        }

        // Check for script tags
        if (preg_match('/<script/i', $content)) {
            return true;
        }

        // Check for other dangerous patterns
        $dangerousPatterns = [
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate secure filename
     */
    protected function generateSecureFilename(UploadedFile $file): string
    {
        $extension = $this->getSecureExtension($file);
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Get secure extension based on actual mime type
     */
    protected function getSecureExtension(UploadedFile $file): string
    {
        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $mimeToExtension[$file->getMimeType()] ?? 'jpg';
    }

    /**
     * Get storage path for image
     */
    protected function getStoragePath(string $filename): string
    {
        $tenantId = $this->getTenantId();
        $datePath = date('Y/m');

        if ($tenantId) {
            return "email-marketing/{$tenantId}/{$datePath}/{$filename}";
        }

        return "email-marketing/{$datePath}/{$filename}";
    }

    /**
     * Delete an image
     */
    public function delete(EmailImage $image): bool
    {
        // Verify tenant ownership
        if ($this->getTenantId() !== $image->tenant_id) {
            return false;
        }

        $image->delete();
        return true;
    }

    /**
     * Get images for current tenant
     */
    public function getImages(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return EmailImage::forTenant($this->getTenantId())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
