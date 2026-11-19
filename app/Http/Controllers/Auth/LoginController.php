<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => 'Format email tidak valid',
            'password.required' => 'Password wajib diisi',
        ]);

        if ($this->tooManyAttempts($request)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan login. Coba lagi nanti.',
            ]);
        }

        if (! Auth::attempt($credentials + ['is_active' => true])) {
            RateLimiter::hit($this->throttleKey($request), 60);

            $request->session()->regenerate();

            throw ValidationException::withMessages([
                'email' => 'Kredensial yang diberikan tidak cocok atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        app(AuditLogger::class)->log('login', 'auth', 'user', auth()->id(), [], []);

        return redirect()->intended($this->homeRoute());
    }

    private function homeRoute(): string
    {
        $user = auth()->user();

        if ($user->can('order.view')) {
            return route('cashier.dashboard');
        }

        if ($user->can('inventory.view')) {
            return route('admin.inventory.index');
        }

        return route('dashboard');
    }

    private function tooManyAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), 5);
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.$request->ip();
    }
}
