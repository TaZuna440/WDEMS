<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RememberDashboardSideController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['side' => ['required', 'in:running,church']]);
        $request->user()->forceFill(['dashboard_side' => $data['side']])->save();

        return redirect()->route('dashboard', ['side' => $data['side']]);
    }
}
