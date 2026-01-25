<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Centralized service for handling all file uploads in the Kolabing application.
 *
 * Supports multiple upload sources (base64, URL, UploadedFile) and organizes
 * files by type and entity ID for consistent storage structure.
 *
 * Storage structure:
 * - profiles/{entity_id}/{filename}
 * - opportunities/{entity_id}/{filename}
 *
 * Supports both local ('public') and cloud ('cloud') storage disks.
 * Configure via FILESYSTEM_UPLOADS_DISK environment variable.
 */
class FileUploadService
{
    /**
     * The storage disk to use for file uploads.
     * Defaults to 'cloud' for Laravel Cloud R2 storage, falls back to 'public' for local.
     */
    private readonly string $storageDisk;

    public function __construct()
    {
        $this->storageDisk = config('filesystems.uploads_disk', 'cloud');
    }

    /**
     * Get the current storage disk name.
     */
    public function getStorageDisk(): string
    {
        return $this->storageDisk;
    }

    /**
     * Upload a file from a base64 encoded string.
     *
     * @param  string  $base64  The base64 encoded image data (with or without data URI prefix)
     * @param  FileUploadType  $type  The type of upload (determines storage location)
     * @param  string  $entityId  The ID of the entity this file belongs to
     * @return string The full public URL of the uploaded file
     *
     * @throws RuntimeException If the upload fails or validation fails
     */
    public function uploadFromBase64(string $base64, FileUploadType $type, string $entityId): string
    {
        // Extract MIME type and decode base64 data
        $imageData = $this->parseBase64Image($base64);

        // Validate MIME type
        $this->validateMimeType($imageData['mime_type'], $type);

        // Validate file size
        $this->validateFileSize(strlen($imageData['data']), $type);

        // Generate filename and path
        $extension = $this->getExtensionFromMimeType($imageData['mime_type']);
        $filename = $this->generateFilename($extension);
        $path = $this->getPath($type, $entityId, $filename);

        // Store the file
        $stored = Storage::disk($this->storageDisk)->put($path, $imageData['data']);

        if (! $stored) {
            Log::error('Failed to upload file from base64', [
                'type' => $type->value,
                'entity_id' => $entityId,
                'path' => $path,
            ]);
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        Log::info('File uploaded from base64', [
            'type' => $type->value,
            'entity_id' => $entityId,
            'path' => $path,
        ]);

        return $this->getUrl($path);
    }

    /**
     * Upload a file from a URL.
     *
     * @param  string  $url  The URL of the image to download and upload
     * @param  FileUploadType  $type  The type of upload (determines storage location)
     * @param  string  $entityId  The ID of the entity this file belongs to
     * @return string The full public URL of the uploaded file
     *
     * @throws RuntimeException If the download fails, upload fails, or validation fails
     */
    public function uploadFromUrl(string $url, FileUploadType $type, string $entityId): string
    {
        // Validate URL format
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL provided.');
        }

        try {
            // Download the file with timeout and size limits
            $response = Http::timeout(30)
                ->withOptions([
                    'stream' => true,
                ])
                ->get($url);

            if (! $response->successful()) {
                throw new RuntimeException("Failed to download file from URL. HTTP status: {$response->status()}");
            }

            $content = $response->body();

            // Detect MIME type from content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($content);

            if ($mimeType === false) {
                throw new RuntimeException('Unable to determine file MIME type.');
            }

            // Validate MIME type
            $this->validateMimeType($mimeType, $type);

            // Validate file size
            $this->validateFileSize(strlen($content), $type);

            // Generate filename and path
            $extension = $this->getExtensionFromMimeType($mimeType);
            $filename = $this->generateFilename($extension);
            $path = $this->getPath($type, $entityId, $filename);

            // Store the file
            $stored = Storage::disk($this->storageDisk)->put($path, $content);

            if (! $stored) {
                throw new RuntimeException('Failed to store the uploaded file.');
            }

            Log::info('File uploaded from URL', [
                'type' => $type->value,
                'entity_id' => $entityId,
                'path' => $path,
                'source_url' => $url,
            ]);

            return $this->getUrl($path);

        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to upload file from URL', [
                'type' => $type->value,
                'entity_id' => $entityId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException("Failed to upload file from URL: {$e->getMessage()}");
        }
    }

    /**
     * Upload a file from an UploadedFile instance.
     *
     * @param  UploadedFile  $file  The uploaded file instance
     * @param  FileUploadType  $type  The type of upload (determines storage location)
     * @param  string  $entityId  The ID of the entity this file belongs to
     * @return string The full public URL of the uploaded file
     *
     * @throws RuntimeException If the upload fails or validation fails
     */
    public function uploadFromFile(UploadedFile $file, FileUploadType $type, string $entityId): string
    {
        // Validate the file is valid
        if (! $file->isValid()) {
            throw new RuntimeException("File upload failed: {$file->getErrorMessage()}");
        }

        // Get MIME type
        $mimeType = $file->getMimeType();
        if ($mimeType === null) {
            throw new RuntimeException('Unable to determine file MIME type.');
        }

        // Validate MIME type
        $this->validateMimeType($mimeType, $type);

        // Validate file size
        $this->validateFileSize($file->getSize(), $type);

        // Generate filename and path
        $extension = $file->getClientOriginalExtension() ?: $this->getExtensionFromMimeType($mimeType);
        $filename = $this->generateFilename($extension);
        $directory = $this->getDirectory($type, $entityId);

        // Store the file
        $storedPath = $file->storeAs($directory, $filename, $this->storageDisk);

        if ($storedPath === false) {
            Log::error('Failed to upload file from UploadedFile', [
                'type' => $type->value,
                'entity_id' => $entityId,
                'original_name' => $file->getClientOriginalName(),
            ]);
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        Log::info('File uploaded from UploadedFile', [
            'type' => $type->value,
            'entity_id' => $entityId,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return $this->getUrl($storedPath);
    }

    /**
     * Delete a file from storage.
     *
     * @param  string  $path  The storage path of the file to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function delete(string $path): bool
    {
        // Handle both full URLs and storage paths
        $storagePath = $this->extractStoragePath($path);

        if (empty($storagePath)) {
            return false;
        }

        if (! Storage::disk($this->storageDisk)->exists($storagePath)) {
            Log::warning('Attempted to delete non-existent file', ['path' => $storagePath]);

            return false;
        }

        $deleted = Storage::disk($this->storageDisk)->delete($storagePath);

        if ($deleted) {
            Log::info('File deleted', ['path' => $storagePath]);
        } else {
            Log::error('Failed to delete file', ['path' => $storagePath]);
        }

        return $deleted;
    }

    /**
     * Get the full public URL for a stored file.
     *
     * @param  string  $path  The storage path of the file
     * @return string The full public URL
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->storageDisk)->url($path);
    }

    /**
     * Get the storage path for a file.
     *
     * @param  FileUploadType  $type  The type of upload
     * @param  string  $entityId  The ID of the entity
     * @param  string  $filename  The filename
     * @return string The full storage path
     */
    public function getPath(FileUploadType $type, string $entityId, string $filename): string
    {
        return "{$type->getStorageDirectory()}/{$entityId}/{$filename}";
    }

    /**
     * Get the storage directory for a type and entity.
     *
     * @param  FileUploadType  $type  The type of upload
     * @param  string  $entityId  The ID of the entity
     * @return string The directory path
     */
    public function getDirectory(FileUploadType $type, string $entityId): string
    {
        return "{$type->getStorageDirectory()}/{$entityId}";
    }

    /**
     * Check if a file exists at the given path.
     *
     * @param  string  $path  The storage path to check
     * @return bool True if the file exists
     */
    public function exists(string $path): bool
    {
        $storagePath = $this->extractStoragePath($path);

        return ! empty($storagePath) && Storage::disk($this->storageDisk)->exists($storagePath);
    }

    /**
     * Parse a base64 encoded image string.
     *
     * @param  string  $base64  The base64 string (with or without data URI prefix)
     * @return array{mime_type: string, data: string} The parsed MIME type and binary data
     *
     * @throws RuntimeException If the base64 string is invalid
     */
    private function parseBase64Image(string $base64): array
    {
        // Check for data URI format: data:image/jpeg;base64,/9j/4AAQ...
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/i', $base64, $matches)) {
            $extension = strtolower($matches[1]);
            $mimeType = "image/{$extension}";
            $data = base64_decode($matches[2], true);

            if ($data === false) {
                throw new RuntimeException('Invalid base64 encoding.');
            }

            return [
                'mime_type' => $mimeType,
                'data' => $data,
            ];
        }

        // Try to decode as raw base64
        $data = base64_decode($base64, true);

        if ($data === false) {
            throw new RuntimeException('Invalid base64 encoding.');
        }

        // Detect MIME type from decoded content
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($data);

        if ($mimeType === false || ! str_starts_with($mimeType, 'image/')) {
            throw new RuntimeException('Unable to determine image MIME type from base64 data.');
        }

        return [
            'mime_type' => $mimeType,
            'data' => $data,
        ];
    }

    /**
     * Validate that the MIME type is allowed for the upload type.
     *
     * @param  string  $mimeType  The MIME type to validate
     * @param  FileUploadType  $type  The upload type
     *
     * @throws RuntimeException If the MIME type is not allowed
     */
    private function validateMimeType(string $mimeType, FileUploadType $type): void
    {
        $allowedMimeTypes = $type->getAllowedMimeTypes();

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            $allowed = implode(', ', $allowedMimeTypes);
            throw new RuntimeException("Invalid file type: {$mimeType}. Allowed types: {$allowed}");
        }
    }

    /**
     * Validate that the file size is within limits.
     *
     * @param  int  $size  The file size in bytes
     * @param  FileUploadType  $type  The upload type
     *
     * @throws RuntimeException If the file size exceeds the limit
     */
    private function validateFileSize(int $size, FileUploadType $type): void
    {
        $maxSize = $type->getMaxFileSize();

        if ($size > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            $actualSizeMB = round($size / (1024 * 1024), 2);
            throw new RuntimeException("File size ({$actualSizeMB}MB) exceeds maximum allowed size ({$maxSizeMB}MB).");
        }
    }

    /**
     * Get file extension from MIME type.
     *
     * @param  string  $mimeType  The MIME type
     * @return string The file extension
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Generate a unique filename.
     *
     * @param  string  $extension  The file extension
     * @return string The generated filename
     */
    private function generateFilename(string $extension): string
    {
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);

        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Extract the storage path from a full URL or path.
     *
     * @param  string  $path  The full URL or storage path
     * @return string The storage path
     */
    private function extractStoragePath(string $path): string
    {
        // If it's a full URL, extract the path after /storage/
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($path);
            $urlPath = $parsed['path'] ?? '';

            // Remove /storage/ prefix if present
            if (str_contains($urlPath, '/storage/')) {
                return substr($urlPath, strpos($urlPath, '/storage/') + 9);
            }

            return '';
        }

        return $path;
    }
}
