<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            Auth::loginUsingId(1);
        }

        return $next($request);
    }
}
