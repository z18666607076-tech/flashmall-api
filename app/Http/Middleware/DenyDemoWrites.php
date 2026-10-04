<?php

namespace App\Http\Middleware;

use App\Demo\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyDemoWrites
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (DemoMode::enabled()) {
            return response()->json([
                'message' => 'The public demo does not allow this change.',
            ], 403);
        }

        return $next($request);
    }
}
