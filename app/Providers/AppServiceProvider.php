<?php

namespace App\Providers;

use App\Services\CartService;
use App\Services\PricingService;
use App\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('kiosk-checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->session()->getId());
        });

        RateLimiter::for('kiosk-cart', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->session()->getId());
        });

        \Illuminate\Support\Facades\View::composer('layouts.kiosk', function (View $view) {
            $cart = app(CartService::class);
            $lines = $cart->lines();
            $subtotal = $cart->subtotal();

            $view->with('layoutCart', [
                'count' => $cart->count(),
                'subtotal' => $subtotal,
                'lines' => $lines,
                'pricing' => app(PricingService::class)->calculate($lines, $subtotal, session('cart.discount_code')),
            ]);
        });

        \Illuminate\Support\Facades\View::composer(['layouts.app', 'layouts.kiosk', 'layouts.guest'], function (View $view): void {
            $settings = app(SettingsService::class)->all();
            $adminPrimary = $this->validHexColor($settings['branding.admin_primary_color'] ?? null, '#c29b3f');
            $customerPrimary = $this->validHexColor($settings['branding.customer_primary_color'] ?? null, '#2d5a42');
            $accent = $this->validHexColor($settings['branding.accent_color'] ?? null, '#b9914b');

            $themeVariables = array_merge(
                $this->paletteVariables('brand', $adminPrimary, [
                    50 => [5, 'white'], 100 => [12, 'white'], 200 => [28, 'white'], 300 => [45, 'white'],
                    400 => [65, 'white'], 500 => [82, 'white'], 600 => [100, 'white'], 700 => [82, 'black'],
                ]),
                $this->paletteVariables('forest', $customerPrimary, [
                    50 => [5, 'white'], 100 => [12, 'white'], 200 => [25, 'white'], 300 => [40, 'white'],
                    400 => [60, 'white'], 500 => [80, 'white'], 600 => [100, 'white'], 700 => [82, 'black'],
                    800 => [65, 'black'], 900 => [45, 'black'], 950 => [28, 'black'],
                ]),
                $this->paletteVariables('gold', $accent, [
                    50 => [5, 'white'], 100 => [12, 'white'], 200 => [26, 'white'], 300 => [42, 'white'],
                    400 => [62, 'white'], 500 => [82, 'white'], 600 => [100, 'white'], 700 => [80, 'black'],
                    800 => [60, 'black'],
                ]),
            );

            $view->with([
                'siteName' => $settings['business.name'] ?? config('app.name'),
                'brandLogo' => $settings['branding.logo'] ?? null,
                'themeVariables' => collect($themeVariables)
                    ->map(fn (string $value, string $key): string => $key.':'.$value)
                    ->implode(';'),
            ]);
        });
    }

    private function validHexColor(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1
            ? $value
            : $fallback;
    }

    /**
     * @param  array<int, array{int, string}>  $shades
     * @return array<string, string>
     */
    private function paletteVariables(string $palette, string $color, array $shades): array
    {
        $variables = [];

        foreach ($shades as $shade => [$colorWeight, $mixColor]) {
            $variables['--color-'.$palette.'-'.$shade] = $colorWeight === 100
                ? $color
                : "color-mix(in srgb, {$color} {$colorWeight}%, {$mixColor})";
        }

        return $variables;
    }
}
