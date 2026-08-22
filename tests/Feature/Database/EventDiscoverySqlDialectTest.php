<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BE-FX-20 — the contract that lets `EventDiscoveryService` keep ONE distance
 * implementation for every driver.
 *
 * The great-circle expression is computed in SQL so that filtering and ordering
 * by distance stay in the database (a bounded LIMIT/OFFSET query, not a whole
 * radius loaded into PHP). That only works while every function it uses is
 * available on the connection under test as well as on production PostgreSQL.
 * SQLite has had these since 3.35 (`SQLITE_ENABLE_MATH_FUNCTIONS`); PostgreSQL
 * has always had them.
 *
 * If this file is the one that fails, the driver is missing a math function —
 * that is the message, not a discovery bug. Do NOT "fix" it by reintroducing a
 * PHP fallback branch: a second implementation is what BE-FX-20 removed.
 */
class EventDiscoverySqlDialectTest extends TestCase
{
    public function test_the_connection_provides_every_function_the_distance_expression_uses(): void
    {
        foreach (['radians(1.0)', 'sin(1.0)', 'cos(1.0)', 'asin(0.5)', 'sqrt(2.0)', 'power(2.0, 2)'] as $call) {
            $value = DB::selectOne("select ({$call}) as v")->v;

            $this->assertIsNumeric(
                $value,
                "The event-discovery distance expression needs SQL {$call}; this driver does not provide it.",
            );
        }
    }

    public function test_the_distance_expression_agrees_with_the_php_haversine_reference(): void
    {
        // 0.1 degrees of latitude, due north — 6371 * radians(0.1) km.
        $sql = '(
            2 * 6371 * asin(
                sqrt(
                    power(sin(radians(? - ?) / 2), 2)
                    + cos(radians(?)) * cos(radians(?))
                    * power(sin(radians(? - ?) / 2), 2)
                )
            )
        )';

        $inSql = (float) DB::selectOne("select {$sql} as v", [
            41.4874, 41.3874, 41.3874, 41.4874, 2.1686, 2.1686,
        ])->v;

        $this->assertEqualsWithDelta(6371.0 * deg2rad(0.1), $inSql, 0.000_001);
    }

    public function test_a_bound_radius_compares_as_a_number_not_as_text(): void
    {
        // PDO binds floats as strings and SQLite orders storage classes before
        // values, so an uncast comparison makes every numeric distance "less than"
        // the radius. Production PostgreSQL infers float8 and is unaffected — which
        // is precisely why an uncast bound would have passed CI and failed nowhere
        // until the filter quietly stopped filtering.
        $this->assertSame(
            0,
            (int) DB::selectOne('select (179.3 <= CAST(? AS double precision)) as v', [50.0])->v,
            'A radius bound as a parameter must compare numerically.',
        );
        $this->assertSame(
            1,
            (int) DB::selectOne('select (11.0 <= CAST(? AS double precision)) as v', [50.0])->v,
        );
    }
}
