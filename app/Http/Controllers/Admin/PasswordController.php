<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:200', 'confirmed'],
        ]);

        $admin = $request->user('admin');
        $admin->password = $data['password'];
        $admin->must_change_password = false;
        $admin->save();

        return redirect()->route('admin.dashboard')->with('success', 'Password updated.');
    }
}
