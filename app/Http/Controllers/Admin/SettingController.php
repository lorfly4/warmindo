<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(protected SettingsService $settings, protected AuditLogger $audit) {}

    public function index(): View
    {
        Gate::authorize('setting.manage');

        $groups = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');
        $allSettings = $this->settings->all();
        $branding = [
            'admin_primary_color' => $allSettings['branding.admin_primary_color'] ?? '#c29b3f',
            'customer_primary_color' => $allSettings['branding.customer_primary_color'] ?? '#2d5a42',
            'accent_color' => $allSettings['branding.accent_color'] ?? '#b9914b',
            'logo' => $allSettings['branding.logo'] ?? null,
        ];

        return view('admin.settings.index', compact('groups', 'branding'));
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('setting.manage');

        $payload = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'branding' => ['required', 'array'],
            'branding.admin_primary_color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'branding.customer_primary_color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'branding.accent_color' => ['required', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
            'brand_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $old = Setting::query()->pluck('value', 'key')->all();
        $newLogoPath = null;

        if ($request->hasFile('brand_logo')) {
            $newLogoPath = $request->file('brand_logo')->store('branding', 'public');

            if (! is_string($newLogoPath)) {
                return back()->withErrors(['brand_logo' => 'Logo gagal disimpan.'])->withInput();
            }
        }

        foreach ($payload['settings'] as $key => $value) {
            $set = Setting::where('key', $key)->first();

            if (! $set) {
                continue;
            }

            $normalized = is_array($value) ? json_encode($value) : $value;

            if ((string) $set->value !== (string) $normalized) {
                $set->update(['value' => $normalized]);
            }
        }

        foreach ([
            'branding.admin_primary_color' => ['Warna utama panel admin', $payload['branding']['admin_primary_color']],
            'branding.customer_primary_color' => ['Warna utama web pelanggan', $payload['branding']['customer_primary_color']],
            'branding.accent_color' => ['Warna aksen', $payload['branding']['accent_color']],
        ] as $key => [$label, $value]) {
            $this->settings->set($key, $value, $label, 'branding');
        }

        if ($newLogoPath !== null) {
            $previousLogo = $this->settings->get('branding.logo');
            $this->settings->set('branding.logo', $newLogoPath, 'Logo bisnis', 'branding');

            if (is_string($previousLogo)) {
                Storage::disk('public')->delete($previousLogo);
            }
        }

        $this->settings->flush();

        $this->audit->log('update', 'settings', 'setting', null, $old, Setting::query()->pluck('value', 'key')->all());

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
