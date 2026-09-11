<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * A self-contained QR encoder, byte mode, error-correction level M, versions 1–10.
 *
 * Written rather than pulled in because a check-in QR is the one asset that must
 * render on a screen with no build step and no third-party dependency, and because
 * the payload is always a short URL — the narrow slice of ISO/IEC 18004 below covers
 * every code this application will ever draw (a v3 code holds 42 bytes at level M;
 * `https://app.kolabing.com/checkin/ABCD1234` is 41).
 *
 * Level M is the deliberate choice: it recovers ~15% damage, which is what a phone
 * camera needs against screen glare and a fingerprint, without inflating the module
 * count the way Q or H would.
 *
 * Correctness is not taken on trust. `QrCodeTest` compares whole matrices against
 * fixtures produced by an independent, widely-used encoder, so a mistake in the
 * Reed–Solomon arithmetic, the interleaving or the mask choice fails the suite
 * instead of quietly producing a square that will not scan.
 */
final class QrCode
{
    /** Byte-mode capacity in bytes at level M, indexed by version. */
    private const CAPACITY = [1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213];

    /**
     * Level-M block structure per version: [EC codewords per block, [[block count, data codewords], …]].
     */
    private const BLOCKS = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    /** Alignment-pattern centre coordinates per version (empty for v1). */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** Level M's two-bit indicator, as used in the format information. */
    private const ECC_LEVEL_BITS = 0b00;

    /** @var array<int, int> GF(256) exponent table. */
    private static array $exp = [];

    /** @var array<int, int> GF(256) logarithm table. */
    private static array $log = [];

    /**
     * The module matrix: `true` is a dark module. Row-major, `[y][x]`.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $payload): array
    {
        $version = self::versionFor($payload);
        $codewords = self::codewords($payload, $version);
        $size = $version * 4 + 17;

        $best = null;
        $bestPenalty = PHP_INT_MAX;

        // The spec picks the mask with the lowest penalty; ties go to the lower
        // pattern number, which is why this walks 0…7 in order and uses `<`.
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::draw($version, $size, $codewords, $mask);
            $penalty = self::penalty($candidate, $size);

            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * An SVG of the code. Sized in modules so the caller can scale it with CSS;
     * `shape-rendering="crispEdges"` keeps module boundaries from blurring, which
     * is what breaks scanning at small sizes.
     */
    public static function svg(string $payload, int $quietZone = 4): string
    {
        $matrix = self::matrix($payload);
        $size = count($matrix);
        $extent = $size + $quietZone * 2;

        // One path for every dark module, merging horizontal runs so the document
        // stays small enough to inline in a page.
        $path = '';
        foreach ($matrix as $y => $row) {
            $x = 0;
            while ($x < $size) {
                if (! $row[$x]) {
                    $x++;

                    continue;
                }

                $run = 0;
                while ($x + $run < $size && $row[$x + $run]) {
                    $run++;
                }

                $path .= sprintf('M%d %dh%dv1h-%dz', $x + $quietZone, $y + $quietZone, $run, $run);
                $x += $run;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" shape-rendering="crispEdges" role="img">'
            .'<rect width="%d" height="%d" fill="#fff"/><path d="%s" fill="#000"/></svg>',
            $extent, $extent, $extent, $extent, $path
        );
    }

    private static function versionFor(string $payload): int
    {
        $length = strlen($payload);

        foreach (self::CAPACITY as $version => $capacity) {
            if ($length <= $capacity) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Payload is too long for a version 1–10 QR code at level M.');
    }

    /**
     * Data + error-correction codewords, interleaved as the spec requires.
     *
     * @return array<int, int>
     */
    private static function codewords(string $payload, int $version): array
    {
        [$ecPerBlock, $groups] = self::BLOCKS[$version];

        $bits = '';
        $bits .= '0100';                                              // byte mode
        $bits .= str_pad(decbin(strlen($payload)), 8, '0', STR_PAD_LEFT); // count, 8 bits below v10
        foreach (str_split($payload) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $dataCodewordCount = 0;
        foreach ($groups as [$count, $dataPerBlock]) {
            $dataCodewordCount += $count * $dataPerBlock;
        }
        $capacityBits = $dataCodewordCount * 8;

        // Terminator, then align to a codeword boundary, then the alternating pad.
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }
        $pad = [0xEC, 0x11];
        $i = 0;
        while (count($data) < $dataCodewordCount) {
            $data[] = $pad[$i++ % 2];
        }

        // Split into blocks, then compute each block's EC codewords.
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($groups as [$count, $dataPerBlock]) {
            for ($b = 0; $b < $count; $b++) {
                $block = array_slice($data, $offset, $dataPerBlock);
                $offset += $dataPerBlock;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecPerBlock);
            }
        }

        // Interleave: column-major across blocks, data first, then EC.
        $out = [];
        $longest = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $longest; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $data
     * @return array<int, int>
     */
    private static function reedSolomon(array $data, int $ecCount): array
    {
        self::initTables();

        // Generator polynomial: (x - a^0)(x - a^1)…(x - a^(ecCount-1)).
        $generator = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= self::mul($coefficient, self::$exp[$i]);
            }
            $generator = $next;
        }

        $remainder = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $lead = $remainder[$i];
            if ($lead === 0) {
                continue;
            }
            foreach ($generator as $j => $coefficient) {
                $remainder[$i + $j] ^= self::mul($coefficient, $lead);
            }
        }

        return array_slice($remainder, count($data), $ecCount);
    }

    private static function initTables(): void
    {
        if (self::$exp !== []) {
            return;
        }

        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // the primitive polynomial QR uses
            }
        }
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    /**
     * @param  array<int, int>  $codewords
     * @return array<int, array<int, bool>>
     */
    private static function draw(int $version, int $size, array $codewords, int $mask): array
    {
        $matrix = array_fill(0, $size, array_fill(0, $size, false));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        $finder = static function (int $originX, int $originY) use (&$matrix, &$reserved, $size): void {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $x = $originX + $dx;
                    $y = $originY + $dy;
                    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
                        continue;
                    }
                    $inRing = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                        && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6);
                    $inCore = $dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4;
                    $matrix[$y][$x] = $inRing || $inCore;
                    $reserved[$y][$x] = true;
                }
            }
        };
        $finder(0, 0);
        $finder($size - 7, 0);
        $finder(0, $size - 7);

        // Timing patterns.
        for ($i = 8; $i < $size - 8; $i++) {
            $dark = $i % 2 === 0;
            $matrix[6][$i] = $dark;
            $reserved[6][$i] = true;
            $matrix[$i][6] = $dark;
            $reserved[$i][6] = true;
        }

        // Alignment patterns, skipping the three finder corners.
        $centres = self::ALIGNMENT[$version];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                $nearFinder = ($cx === 6 && $cy === 6)
                    || ($cx === 6 && $cy === $size - 7)
                    || ($cx === $size - 7 && $cy === 6);
                if ($nearFinder) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $matrix[$cy + $dy][$cx + $dx] = max(abs($dx), abs($dy)) !== 1;
                        $reserved[$cy + $dy][$cx + $dx] = true;
                    }
                }
            }
        }

        // Dark module, and the format-information areas.
        $matrix[$size - 8][8] = true;
        $reserved[$size - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            if (! $reserved[8][$i]) {
                $reserved[8][$i] = true;
            }
            if (! $reserved[$i][8]) {
                $reserved[$i][8] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        if ($version >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $reserved[$size - 11 + $j][$i] = true;
                    $reserved[$i][$size - 11 + $j] = true;
                }
            }
        }

        // Data placement: two-module columns, right to left, alternating direction.
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }
        $bitIndex = 0;
        $upward = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--; // the vertical timing pattern is not a data column
            }
            for ($step = 0; $step < $size; $step++) {
                $y = $upward ? $size - 1 - $step : $step;
                foreach ([$right, $right - 1] as $x) {
                    if ($reserved[$y][$x]) {
                        continue;
                    }
                    $bit = $bitIndex < strlen($bits) ? $bits[$bitIndex] === '1' : false;
                    $bitIndex++;
                    $matrix[$y][$x] = $bit !== self::maskAt($mask, $x, $y);
                }
            }
            $upward = ! $upward;
        }

        self::writeFormatInfo($matrix, $size, $mask);

        if ($version >= 7) {
            self::writeVersionInfo($matrix, $size, $version);
        }

        return $matrix;
    }

    /** The eight mask conditions; true means "invert this module". */
    private static function maskAt(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => ($y * $x) % 2 + ($y * $x) % 3 === 0,
            6 => (($y * $x) % 2 + ($y * $x) % 3) % 2 === 0,
            7 => ((($y + $x) % 2) + ($y * $x) % 3) % 2 === 0,
            default => throw new InvalidArgumentException('Unknown mask pattern.'),
        };
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function writeFormatInfo(array &$matrix, int $size, int $mask): void
    {
        $data = (self::ECC_LEVEL_BITS << 3) | $mask;
        $value = $data << 10;
        for ($i = 4; $i >= 0; $i--) {
            if ($value & (1 << ($i + 10))) {
                $value ^= 0x537 << $i;
            }
        }
        $format = (($data << 10) | $value) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($format >> $i) & 1);

            // Copy one: around the top-left finder.
            if ($i < 6) {
                $matrix[8][$i] = $bit;
            } elseif ($i === 6) {
                $matrix[8][7] = $bit;
            } elseif ($i === 7) {
                $matrix[8][8] = $bit;
            } elseif ($i === 8) {
                $matrix[7][8] = $bit;
            } else {
                $matrix[14 - $i][8] = $bit;
            }

            // Copy two: split between the other two finders.
            if ($i < 8) {
                $matrix[8][$size - 1 - $i] = $bit;
            } else {
                $matrix[$size - 15 + $i][8] = $bit;
            }
        }
    }

    /**
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function writeVersionInfo(array &$matrix, int $size, int $version): void
    {
        $value = $version << 12;
        for ($i = 5; $i >= 0; $i--) {
            if ($value & (1 << ($i + 12))) {
                $value ^= 0x1F25 << $i;
            }
        }
        $info = ($version << 12) | $value;

        for ($i = 0; $i < 18; $i++) {
            $bit = (bool) (($info >> $i) & 1);
            $matrix[$size - 11 + $i % 3][intdiv($i, 3)] = $bit;
            $matrix[intdiv($i, 3)][$size - 11 + $i % 3] = $bit;
        }
    }

    /**
     * The four penalty rules. Lower is better; this is what decides the mask.
     *
     * @param  array<int, array<int, bool>>  $matrix
     */
    private static function penalty(array $matrix, int $size): int
    {
        $penalty = 0;

        // Rule 1: runs of five or more same-coloured modules.
        for ($i = 0; $i < $size; $i++) {
            foreach ([true, false] as $horizontal) {
                $run = 1;
                for ($j = 1; $j < $size; $j++) {
                    $current = $horizontal ? $matrix[$i][$j] : $matrix[$j][$i];
                    $previous = $horizontal ? $matrix[$i][$j - 1] : $matrix[$j - 1][$i];
                    if ($current === $previous) {
                        $run++;

                        continue;
                    }
                    if ($run >= 5) {
                        $penalty += 3 + ($run - 5);
                    }
                    $run = 1;
                }
                if ($run >= 5) {
                    $penalty += 3 + ($run - 5);
                }
            }
        }

        // Rule 2: 2×2 blocks of one colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $value = $matrix[$y][$x];
                if ($matrix[$y][$x + 1] === $value && $matrix[$y + 1][$x] === $value && $matrix[$y + 1][$x + 1] === $value) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: the 1:1:3:1:1 finder-like pattern with four light modules either side.
        $pattern = [true, false, true, true, true, false, true];
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 7; $j++) {
                foreach ([true, false] as $horizontal) {
                    $matches = true;
                    for ($k = 0; $k < 7; $k++) {
                        $module = $horizontal ? $matrix[$i][$j + $k] : $matrix[$j + $k][$i];
                        if ($module !== $pattern[$k]) {
                            $matches = false;
                            break;
                        }
                    }
                    if (! $matches) {
                        continue;
                    }

                    $before = true;
                    for ($k = $j - 4; $k < $j; $k++) {
                        if ($k < 0) {
                            continue;
                        }
                        if ($horizontal ? $matrix[$i][$k] : $matrix[$k][$i]) {
                            $before = false;
                            break;
                        }
                    }
                    $after = true;
                    for ($k = $j + 7; $k < $j + 11; $k++) {
                        if ($k >= $size) {
                            continue;
                        }
                        if ($horizontal ? $matrix[$i][$k] : $matrix[$k][$i]) {
                            $after = false;
                            break;
                        }
                    }
                    if ($before || $after) {
                        $penalty += 40;
                    }
                }
            }
        }

        // Rule 4: deviation from an even light/dark split.
        $dark = 0;
        foreach ($matrix as $row) {
            foreach ($row as $module) {
                if ($module) {
                    $dark++;
                }
            }
        }
        $percent = (int) (abs($dark * 100 / ($size * $size) - 50) / 5);
        $penalty += $percent * 10;

        return $penalty;
    }
}
