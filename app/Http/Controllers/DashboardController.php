<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = auth()->user();

        if ($user->can('order.view')) {
            return redirect()->route('cashier.dashboard');
        }

        if ($user->can('inventory.view')) {
            return redirect()->route('admin.inventory.index');
        }

        abort(403);
    }
}
