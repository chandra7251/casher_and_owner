<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintBridgeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.print_bridge.token');
        $provided = (string) $request->bearerToken();
        abort_unless($configured !== '' && hash_equals($configured, $provided), 401, 'Print bridge token tidak valid.');

        return $next($request);
    }
}
