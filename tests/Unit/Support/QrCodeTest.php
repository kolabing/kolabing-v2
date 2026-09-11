<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\QrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The encoder is only useful if a phone camera can read what it draws, and that is
 * not something a PHP assertion can check directly. So the fixtures below are output
 * this encoder produced and an **independent decoder** (jsQR, the ZXing-derived
 * reader) read back to the exact payload on 2026-08-21, at three versions covering
 * the structural range: v1 (no alignment pattern), v3 (one alignment pattern) and
 * v7 (the first version carrying version-information blocks).
 *
 * Freezing verified output means any change to the Reed–Solomon arithmetic, the
 * interleaving, the placement walk or the mask choice fails here — instead of
 * shipping a square that silently will not scan.
 *
 * To re-verify after an intentional change, regenerate the fixture and decode it
 * with an outside reader before committing.
 */
class QrCodeTest extends TestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function fixtures(): array
    {
        $path = __DIR__.'/../../Fixtures/qr-matrices.json';

        $this->assertFileExists($path);

        /** @var array<string, array<int, string>> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true);

        return $decoded;
    }

    public function test_it_reproduces_matrices_a_real_decoder_has_verified(): void
    {
        foreach ($this->fixtures() as $payload => $expected) {
            $actual = array_map(
                fn (array $row): string => implode('', array_map(fn (bool $dark): string => $dark ? '1' : '0', $row)),
                QrCode::matrix($payload)
            );

            $this->assertSame($expected, $actual, 'matrix drifted for payload: '.$payload);
        }
    }

    public function test_the_module_count_matches_the_version(): void
    {
        // Side length is always 4·version + 17.
        $this->assertCount(21, QrCode::matrix('a'));                 // v1
        $this->assertCount(29, QrCode::matrix(str_repeat('x', 42))); // v3, exactly at capacity
        $this->assertCount(33, QrCode::matrix(str_repeat('x', 43))); // v4, one byte over
    }

    public function test_the_three_finder_patterns_are_where_a_reader_looks_for_them(): void
    {
        $matrix = QrCode::matrix('https://app.kolabing.com/checkin/ABCD1234');
        $size = count($matrix);

        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$originX, $originY]) {
            $this->assertTrue($matrix[$originY][$originX], 'finder corner missing');
            $this->assertFalse($matrix[$originY + 1][$originX + 1], 'finder ring not light');
            $this->assertTrue($matrix[$originY + 3][$originX + 3], 'finder core not dark');
        }

        for ($i = 8; $i < $size - 8; $i++) {
            $this->assertSame($i % 2 === 0, $matrix[6][$i], "row-6 timing module {$i}");
            $this->assertSame($i % 2 === 0, $matrix[$i][6], "column-6 timing module {$i}");
        }

        $this->assertTrue($matrix[$size - 8][8], 'the dark module is missing');
    }

    public function test_the_svg_is_self_contained_and_has_a_quiet_zone(): void
    {
        $svg = QrCode::svg('https://app.kolabing.com/checkin/ABCD1234');

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('viewBox="0 0 37 37"', $svg); // 29 modules + 4 quiet each side
        $this->assertStringContainsString('shape-rendering="crispEdges"', $svg);
        $this->assertStringContainsString('<path d="M', $svg);
        // Nothing external: it has to render under a strict CSP.
        $this->assertStringNotContainsString('http://', str_replace('http://www.w3.org/2000/svg', '', $svg));
    }

    public function test_a_payload_too_long_for_version_ten_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QrCode::matrix(str_repeat('x', 214));
    }
}
