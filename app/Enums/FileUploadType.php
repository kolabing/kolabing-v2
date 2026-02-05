<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum representing different types of file uploads in the application.
 * Each type maps to a specific storage directory structure.
 */
enum FileUploadType: string
{
    case ProfilePhoto = 'profile_photo';
    case OpportunityPhoto = 'opportunity_photo';
    case GalleryPhoto = 'gallery_photo';
    case EventPhoto = 'event_photo';

    /**
     * Get the storage directory for this upload type.
     *
     * @return string The directory path within the public disk
     */
    public function getStorageDirectory(): string
    {
        return match ($this) {
            self::ProfilePhoto => 'profiles',
            self::OpportunityPhoto => 'opportunities',
            self::GalleryPhoto => 'gallery',
            self::EventPhoto => 'events',
        };
    }

    /**
     * Get the maximum allowed file size in bytes for this upload type.
     *
     * @return int Maximum file size in bytes
     */
    public function getMaxFileSize(): int
    {
        return match ($this) {
            self::ProfilePhoto => 5 * 1024 * 1024, // 5MB
            self::OpportunityPhoto => 5 * 1024 * 1024, // 5MB
            self::GalleryPhoto => 5 * 1024 * 1024, // 5MB
            self::EventPhoto => 5 * 1024 * 1024, // 5MB
        };
    }

    /**
     * Get the allowed MIME types for this upload type.
     *
     * @return array<string> Array of allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return match ($this) {
            self::ProfilePhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
            ],
        };
    }

    /**
     * Get the allowed file extensions for this upload type.
     *
     * @return array<string> Array of allowed file extensions
     */
    public function getAllowedExtensions(): array
    {
        return match ($this) {
            self::ProfilePhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto => [
                'jpeg',
                'jpg',
                'png',
                'gif',
                'webp',
            ],
        };
    }

    /**
     * Get all enum values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
