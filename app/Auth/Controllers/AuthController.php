<?php

namespace App\Auth\Controllers;

use App\Auth\Models\PASWORD;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'userid' => 'required|integer',
            'password' => 'required|string',
        ]);

        $userid = $request->input('userid');
        $password = $request->input('password');

        $user = PASWORD::where('社員CD', $userid)->first();

        if ($user && $user->PASSWORD === $password) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();

            return redirect()->route('landing-page');
        }

        $worker = \App\Inspection\Models\WorkerLogin::where('EmployeeID', $userid)->first();

        if ($worker && $worker->Password === $password) {
            Auth::guard('worker')->login($worker);
            $request->session()->regenerate();
            return redirect()->route('landing-page');
        }

        return back()->withErrors([
            'credentials' => 'Incorrect credentials',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        Auth::guard('worker')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
