<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageMediaFeatureTest extends TestCase
{
    public function test_public_storage_route_streams_images_without_a_symlink(): void
    {
        Storage::fake('public');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jM9sAAAAASUVORK5CYII=');
        Storage::disk('public')->put('products/menu.png', $image);

        $response = $this->get('/media/products/menu.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame($image, $response->streamedContent());
    }

    public function test_public_storage_route_does_not_serve_non_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/readme.txt', 'private-looking content');

        $this->get('/media/products/readme.txt')->assertNotFound();
    }
}
