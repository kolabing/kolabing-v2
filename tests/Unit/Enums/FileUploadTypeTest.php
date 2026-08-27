<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FileUploadType;
use PHPUnit\Framework\TestCase;

/**
 * Every case must be answered by every method.
 *
 * `CoverPhoto` was added in #239 and reached two of the four `match`
 * statements: it had a storage directory and a size limit, but no MIME types
 * and no extensions. So `FileUploadService::validateMimeType()` hit an
 * `UnhandledMatchError` and every cover upload answered **500** — the app said
 * "Could not add that photo" and nobody could tell why from the client side.
 *
 * A match on an enum is exhaustive only until someone adds a case. This test is
 * the thing that notices.
 */
class FileUploadTypeTest extends TestCase
{
    /**
     * @return array<string, array{FileUploadType}>
     */
    public static function cases(): array
    {
        $out = [];

        foreach (FileUploadType::cases() as $case) {
            $out[$case->value] = [$case];
        }

        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_every_case_has_a_storage_directory(FileUploadType $type): void
    {
        $this->assertNotSame('', $type->getStorageDirectory());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_every_case_has_a_size_limit(FileUploadType $type): void
    {
        $this->assertGreaterThan(0, $type->getMaxFileSize());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_every_case_has_allowed_mime_types(FileUploadType $type): void
    {
        // The call itself is the assertion: an unhandled case throws here.
        $this->assertNotEmpty($type->getAllowedMimeTypes());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_every_case_has_allowed_extensions(FileUploadType $type): void
    {
        $this->assertNotEmpty($type->getAllowedExtensions());
    }

    public function test_the_cover_photo_accepts_the_image_types_a_phone_produces(): void
    {
        $mimes = FileUploadType::CoverPhoto->getAllowedMimeTypes();

        $this->assertContains('image/jpeg', $mimes);
        $this->assertContains('image/png', $mimes);
        $this->assertContains('jpg', FileUploadType::CoverPhoto->getAllowedExtensions());
        $this->assertSame('covers', FileUploadType::CoverPhoto->getStorageDirectory());
    }
}
