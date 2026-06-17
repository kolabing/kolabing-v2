<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Icon;
use App\Models\User;
use Database\Seeders\IconSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IconLibraryAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function svgUpload(string $name = 'My Brunch Icon.svg'): UploadedFile
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>';

        return File::createWithContent($name, $svg)->mimeType('image/svg+xml');
    }

    public function test_seeder_syncs_bundled_svgs_and_creates_one_row_each(): void
    {
        Storage::fake('public');
        // Non-SVG files in the public dir are ignored.
        Storage::disk('public')->put('category-icons/notes.txt', 'ignore me');

        $this->seed(IconSeeder::class);

        // One row per committed source SVG (database/seeders/icons/*.svg).
        $sourceCount = collect(\Illuminate\Support\Facades\File::files(database_path('seeders/icons')))
            ->filter(fn ($f): bool => strtolower($f->getExtension()) === 'svg')
            ->count();

        $this->assertGreaterThan(0, $sourceCount);
        $this->assertSame($sourceCount, Icon::query()->count());

        $running = Icon::query()->where('slug', 'running')->first();
        $this->assertNotNull($running);
        $this->assertSame('Running', $running->label);
        $this->assertSame('category-running.svg', $running->filename);
        $this->assertSame(Icon::SOURCE_BUNDLED, $running->source);
        $this->assertStringContainsString('category-icons/category-running.svg', $running->url);
        // The source SVG was copied into the public disk (web-served after storage:link).
        Storage::disk('public')->assertExists('category-icons/category-running.svg');
    }

    public function test_seeder_is_idempotent(): void
    {
        Storage::fake('public');
        $this->seed(IconSeeder::class);
        $first = Icon::query()->count();
        $this->seed(IconSeeder::class);
        $this->assertSame($first, Icon::query()->count());
    }

    public function test_index_renders_library(): void
    {
        Storage::fake('public');
        $icon = Icon::factory()->bundled()->create(['slug' => 'running', 'label' => 'Running', 'filename' => 'category-running.svg']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.icons.index'))
            ->assertOk()
            ->assertSee('Running')
            ->assertSee($icon->url, false);
    }

    public function test_upload_creates_row_and_file(): void
    {
        Storage::fake('public');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.icons.store'), [
                'label' => 'Brunch',
                'icon_svg' => $this->svgUpload(),
            ])->assertRedirect(route('admin.icons.index'));

        $icon = Icon::query()->where('label', 'Brunch')->first();
        $this->assertNotNull($icon);
        $this->assertSame(Icon::SOURCE_UPLOADED, $icon->source);
        $this->assertSame('my-brunch-icon', $icon->slug); // filename sanitised/slugified
        $this->assertSame('category-my-brunch-icon.svg', $icon->filename);
        Storage::disk('public')->assertExists('category-icons/'.$icon->filename);
    }

    public function test_upload_rejects_non_svg(): void
    {
        Storage::fake('public');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.icons.store'), [
                'icon_svg' => UploadedFile::fake()->image('photo.png'),
            ])->assertSessionHasErrors('icon_svg');

        $this->assertSame(0, Icon::query()->count());
    }

    public function test_upload_keeps_slugs_unique(): void
    {
        Storage::fake('public');
        Icon::factory()->create(['slug' => 'brunch', 'filename' => 'category-brunch.svg']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.icons.store'), [
                'icon_svg' => $this->svgUpload('brunch.svg'),
            ])->assertRedirect();

        $this->assertSame('brunch-2', Icon::query()->latest('id')->where('source', Icon::SOURCE_UPLOADED)->first()?->slug);
    }
}
