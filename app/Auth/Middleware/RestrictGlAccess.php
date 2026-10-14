<?php

namespace App\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGlAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ang GL na pinayagan lang sa GL table ay sa confirmation page lang
        if ($request->session()->get('access') === 'gl') {
            return redirect()->route('lot-confirmation');
        }

        return $next($request);
    }
}