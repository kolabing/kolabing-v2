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
    case CoverPhoto = 'cover_photo';
    case KolabMedia = 'kolab_media';
    case OpportunityPhoto = 'opportunity_photo';
    case GalleryPhoto = 'gallery_photo';
    case EventPhoto = 'event_photo';

    /**
     * The photo two attendees took while completing a challenge together
     * (kolabing-v2#216). Images only — a proof photo is a photo, and allowing
     * video here would put 50 MB uploads behind a button people press at events
     * on venue wifi.
     */
    case ChallengeProof = 'challenge_proof';

    /**
     * A video in the profile gallery, today only ever imported from Instagram
     * (BE-NF-75). Same `gallery/` directory as GalleryPhoto; its poster frame is
     * stored as a GalleryPhoto. The ceiling matches KolabMedia; the import applies
     * the lower `services.instagram.max_video_mb` cap on top.
     */
    case GalleryVideo = 'gallery_video';

    /**
     * Get the storage directory for this upload type.
     *
     * @return string The directory path within the public disk
     */
    public function getStorageDirectory(): string
    {
        return match ($this) {
            self::ProfilePhoto => 'profiles',
            self::CoverPhoto => 'covers',
            self::KolabMedia => 'kolabs',
            self::OpportunityPhoto => 'opportunities',
            self::GalleryPhoto => 'gallery',
            self::GalleryVideo => 'gallery',
            self::EventPhoto => 'events',
            self::ChallengeProof => 'challenge-proofs',
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
            self::CoverPhoto => 5 * 1024 * 1024, // 5MB
            self::KolabMedia => 50 * 1024 * 1024, // 50MB
            self::OpportunityPhoto => 5 * 1024 * 1024, // 5MB
            self::GalleryPhoto => 5 * 1024 * 1024, // 5MB
            self::GalleryVideo => 50 * 1024 * 1024, // 50MB
            self::EventPhoto => 5 * 1024 * 1024, // 5MB
            self::ChallengeProof => 5 * 1024 * 1024, // 5MB
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
            self::ProfilePhoto, self::CoverPhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto, self::ChallengeProof => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
            ],
            self::GalleryVideo => [
                'video/mp4',
                'video/quicktime',
                'video/webm',
            ],
            self::KolabMedia => [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'video/mp4',
                'video/quicktime',
                'video/webm',
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
            self::ProfilePhoto, self::CoverPhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto, self::ChallengeProof => [
                'jpeg',
                'jpg',
                'png',
                'gif',
                'webp',
            ],
            self::GalleryVideo => [
                'mp4',
                'mov',
                'webm',
            ],
            self::KolabMedia => [
                'jpeg',
                'jpg',
                'png',
                'gif',
                'webp',
                'mp4',
                'mov',
                'webm',
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
