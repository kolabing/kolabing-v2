<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\City;
use App\Services\CityResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * BE-FX-60: Google Places answers with the local spelling of a city, and in a
 * metro area with the borough. The picker offers the canonical `cities.name`.
 * The resolver is what stops those two from being different cities.
 */
class CityResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private CityResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(CityResolver::class);
        $this->resolver->flush();
    }

    public function test_normalize_strips_case_accents_and_punctuation(): void
    {
        $this->assertSame('ciudad de mexico', CityResolver::normalize('Ciudad de México'));
        $this->assertSame('ciudad de mexico', CityResolver::normalize('  CIUDAD DE MEXICO  '));
        $this->assertSame('mexico d f', CityResolver::normalize('México D.F.'));
        $this->assertSame('', CityResolver::normalize('   '));
    }

    public function test_google_spellings_of_mexico_city_resolve_to_the_canonical_name(): void
    {
        foreach (['Ciudad de México', 'ciudad de mexico', 'CDMX', 'México D.F.', 'Mexico City'] as $spelling) {
            $this->assertSame('Mexico City', $this->resolver->canonicalFor($spelling), $spelling);
        }
    }

    public function test_a_mexico_city_borough_resolves_to_mexico_city(): void
    {
        // Google returns the alcaldía as the `locality` for a CDMX address —
        // this is the exact value that shipped on a real listing.
        $this->assertSame('Mexico City', $this->resolver->canonicalFor('Cuajimalps'));
        $this->assertSame('Mexico City', $this->resolver->canonicalFor('Cuajimalpa de Morelos'));
        $this->assertSame('Mexico City', $this->resolver->canonicalFor('Coyoacán'));
    }

    public function test_accented_spanish_city_names_resolve_without_an_alias_entry(): void
    {
        City::factory()->create(['name' => 'Malaga', 'country' => 'Spain']);
        $this->resolver->flush();

        $this->assertSame('Malaga', $this->resolver->canonicalFor('Málaga'));
    }

    public function test_an_unknown_city_resolves_to_null_but_is_still_storable(): void
    {
        $this->assertNull($this->resolver->canonicalFor('Atlantis'));
        $this->assertSame('Atlantis', $this->resolver->storableName('  Atlantis  '));
        $this->assertSame('Mexico City', $this->resolver->storableName('Ciudad de México'));
        $this->assertNull($this->resolver->storableName('   '));
    }

    public function test_resolve_prefers_an_explicit_city_id(): void
    {
        $city = City::factory()->create(['name' => 'Mexico City', 'country' => 'Mexico']);
        $this->resolver->flush();

        $this->assertTrue($city->is($this->resolver->resolve($city->id, 'Barcelona')));
        $this->assertTrue($city->is($this->resolver->resolve(null, 'Ciudad de México')));
        $this->assertNull($this->resolver->resolve(null, null));
    }

    public function test_matching_names_covers_every_spelling_already_in_the_database(): void
    {
        $names = $this->resolver->matchingNames('Mexico City');

        $this->assertContains('Mexico City', $names);
        $this->assertContains('Ciudad de México', $names);
        $this->assertContains('Cuajimalps', $names);
    }

    public function test_matching_names_of_an_unknown_city_is_just_that_city(): void
    {
        $this->assertSame(['Atlantis'], $this->resolver->matchingNames(' Atlantis '));
    }

    public function test_same_city_compares_across_spellings(): void
    {
        $this->assertTrue($this->resolver->sameCity('Mexico City', 'Ciudad de México'));
        $this->assertTrue($this->resolver->sameCity('Atlantis', 'atlantis'));
        $this->assertFalse($this->resolver->sameCity('Mexico City', 'Barcelona'));
        $this->assertFalse($this->resolver->sameCity(null, 'Barcelona'));
    }
}
