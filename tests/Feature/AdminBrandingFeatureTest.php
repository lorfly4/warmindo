<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBrandingFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedStaffRolesAndSettings();
    }

    public function test_admin_can_customize_brand_colors_and_business_logo(): void
    {
        Storage::fake('public');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jM9sAAAAASUVORK5CYII=');

        $this->actAsFresh($this->adminUser())
            ->put(route('admin.settings.update'), [
                'settings' => ['business.name' => 'Rumah Makan Jakarta'],
                'branding' => [
                    'admin_primary_color' => '#123456',
                    'customer_primary_color' => '#345678',
                    'accent_color' => '#abcdef',
                ],
                'brand_logo' => UploadedFile::fake()->createWithContent('logo.png', $image),
            ])
            ->assertSessionHas('success', 'Pengaturan berhasil disimpan.');

        $this->assertDatabaseHas('settings', ['key' => 'branding.admin_primary_color', 'value' => '#123456']);
        $this->assertDatabaseHas('settings', ['key' => 'branding.customer_primary_color', 'value' => '#345678']);
        $this->assertDatabaseHas('settings', ['key' => 'branding.accent_color', 'value' => '#abcdef']);
        $this->assertDatabaseHas('settings', ['key' => 'business.name', 'value' => 'Rumah Makan Jakarta']);

        $logoPath = Setting::query()->where('key', 'branding.logo')->value('value');
        $this->assertIsString($logoPath);
        Storage::disk('public')->assertExists($logoPath);

        $this->get(route('home'))
            ->assertSee('Rumah Makan Jakarta')
            ->assertSee('--color-brand-600:#123456', false)
            ->assertSee(route('media.show', ['path' => $logoPath]), false);
    }
}
