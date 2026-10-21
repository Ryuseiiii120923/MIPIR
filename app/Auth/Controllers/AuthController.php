<?php

namespace App\Auth\Controllers;

use App\Auth\Models\PASWORD;
use App\Domain\Worker\Staff;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Inspection\Models\WorkerLogin;
use App\Inspection\Repositories\LotConfirmationRepository;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $allowedPositions = [
            'Staff',
            'Officer',
            'Sr. Engineer',
            'Jr. Engineer',
            'Cadet Engineer',
            'Supervisor',
            'Coordinator',
            'Nurse',
            'Manager',
            'Deputy Manager',
            'Head',
            'Deputy Head',
            'Sr. Head',
            'General Manager',
        ];

        $request->validate([
            'userid'   => 'required|integer',
            'password' => 'required|string',
        ]);

        $userid   = $request->input('userid');
        $password = $request->input('password');

        if ($this->isDummyLogin((int) $userid, $password)) {
            Auth::guard('web')->login($this->dummyStaff());
            $request->session()->regenerate();

            Log::warning('Dummy login used', ['ip' => $request->ip()]);

            return redirect()->route('landing-page');
        }

        $isStaff = Staff::where('staffNo', $userid)
            ->whereIn('newPos', $allowedPositions)
            ->exists();

        $isGl = ! $isStaff && app(LotConfirmationRepository::class)->isGl((string) $userid);

        if ($isStaff || $isGl) {
            $user = PASWORD::where('社員CD', $userid)->first();

            if ($user && $user->PASSWORD === $password) {
                Auth::guard('web')->login($user);
                $request->session()->regenerate();

                $request->session()->put('access', $isGl ? 'gl' : 'staff');

                if ($isGl) {
                    // GL: ask where to go (lot confirmation or inspection)
                    return redirect()
                        ->route('landing-page')
                        ->with('gl_choice', true);
                }

                return redirect()->route('landing-page');
            }

            return back()
                ->withInput($request->only('userid'))
                ->withErrors(['credentials' => 'Incorrect credentials']);
        }

        $worker = WorkerLogin::where('EmployeeID', $userid)->first();

        if (! $worker) {
            return back()
                ->withInput($request->only('userid'))
                ->withErrors([
                    'credentials' => 'This inspector is not registered. Please inform the staff.',
                ]);
        }

        if ($worker->Password === $password) {
            Auth::guard('worker')->login($worker);
            $request->session()->regenerate();

            return redirect()->route('landing-page');
        }

        return back()
            ->withInput($request->only('userid'))
            ->withErrors(['credentials' => 'Incorrect credentials']);
    }

    private function isDummyLogin(int $userid, string $password): bool
    {
        $expected = (string) config('dummy-login.password');

        // Walang password sa .env = naka-off, kahit enabled
        if (! config('dummy-login.enabled') || $expected === '') {
            return false;
        }

        return $userid === (int) config('dummy-login.userid')
            && hash_equals($expected, $password);
    }

    private function dummyStaff(): PASWORD
    {
        $id = config('dummy-login.userid');

        $user = PASWORD::where('社員CD', $id)->first();

        if ($user === null) {
            $user = (new PASWORD())->forceFill([
                '社員CD'   => $id,
                'PASSWORD' => config('dummy-login.password'),
            ]);
            $user->save();
        }

        return $user;
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
