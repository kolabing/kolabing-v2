<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\OfferOption;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferOptionLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> */
    public static function endpoints(): array
    {
        return [
            'offerings' => ['api.v1.lookup.offerings', OfferOption::KIND_OFFERING],
            'deliverables' => ['api.v1.lookup.deliverables', OfferOption::KIND_DELIVERABLE],
            'needs' => ['api.v1.lookup.needs', OfferOption::KIND_NEED],
            'product-types' => ['api.v1.lookup.product-types', OfferOption::KIND_PRODUCT_TYPE],
            'venue-types' => ['api.v1.lookup.venue-types', OfferOption::KIND_VENUE_TYPE],
            'goals' => ['api.v1.lookup.goals', OfferOption::KIND_GOAL],
            'product-interactions' => ['api.v1.lookup.product-interactions', OfferOption::KIND_PRODUCT_INTERACTION],
            'venue-fits' => ['api.v1.lookup.venue-fits', OfferOption::KIND_VENUE_FIT],
            'kolab-highlights' => ['api.v1.lookup.kolab-highlights', OfferOption::KIND_KOLAB_HIGHLIGHT],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_lookup_returns_active_options_sorted(string $route, string $kind): void
    {
        OfferOption::query()->create(['kind' => $kind, 'slug' => 'zzz', 'name' => 'ZZZ', 'sort_order' => 2, 'is_active' => true]);
        OfferOption::query()->create(['kind' => $kind, 'slug' => 'aaa', 'name' => 'AAA', 'icon' => 'star', 'icon_url' => 'http://localhost/storage/category-icons/category-running.svg', 'sort_order' => 1, 'is_active' => true]);
        OfferOption::query()->create(['kind' => $kind, 'slug' => 'hidden', 'name' => 'Hidden', 'sort_order' => 3, 'is_active' => false]);
        // Option of a different kind must not leak in.
        $otherKind = $kind === OfferOption::KIND_NEED ? OfferOption::KIND_OFFERING : OfferOption::KIND_NEED;
        OfferOption::query()->create(['kind' => $otherKind, 'slug' => 'leak', 'name' => 'Leak', 'is_active' => true]);

        $response = $this->getJson(route($route))->assertOk();

        $response->assertJsonPath('success', true);
        $data = $response->json('data');

        $this->assertCount(2, $data); // active only
        $this->assertSame('aaa', $data[0]['value']); // sort_order honoured
        $this->assertSame('zzz', $data[1]['value']);
        $this->assertSame('AAA', $data[0]['label']);
        $this->assertSame('star', $data[0]['icon']);
        // icon_url is the full public URL of the picked personalised SVG.
        $this->assertSame('http://localhost/storage/category-icons/category-running.svg', $data[0]['icon_url']);
        $this->assertNull($data[1]['icon_url']); // null when unset
        $this->assertTrue($data[0]['is_active']);
        $this->assertSame(1, $data[0]['sort_order']);
        // Shared shape: exactly value/label/icon/icon_url/is_active/sort_order.
        $this->assertSame(
            ['value', 'label', 'icon', 'icon_url', 'is_active', 'sort_order'],
            array_keys($data[0])
        );
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_lookup_endpoints_are_public(): void
    {
        $this->getJson(route('api.v1.lookup.offerings'))->assertOk();
        $this->getJson(route('api.v1.lookup.deliverables'))->assertOk();
        $this->getJson(route('api.v1.lookup.needs'))->assertOk();
        $this->getJson(route('api.v1.lookup.product-types'))->assertOk();
        $this->getJson(route('api.v1.lookup.venue-types'))->assertOk();
        $this->getJson(route('api.v1.lookup.goals'))->assertOk();
        $this->getJson(route('api.v1.lookup.product-interactions'))->assertOk();
        $this->getJson(route('api.v1.lookup.venue-fits'))->assertOk();
        $this->getJson(route('api.v1.lookup.kolab-highlights'))->assertOk();
    }
}
