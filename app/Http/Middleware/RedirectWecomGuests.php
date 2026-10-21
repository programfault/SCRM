<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWecomGuests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldRedirect($request)) {
            return redirect()->route('wecom.start');
        }

        return $next($request);
    }

    private function shouldRedirect(Request $request): bool
    {
        if ($request->user() !== null || $request->is('wecom', 'wecom/*')) {
            return false;
        }

        return str_contains(strtolower((string) $request->userAgent()), 'wxwork');
    }
}
