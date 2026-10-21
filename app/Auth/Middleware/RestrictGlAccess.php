<?php

namespace App\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictGlAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('access') === 'gl') {
            return redirect()->route('lot-confirmation');
        }

        return $next($request);
    }
}