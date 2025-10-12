<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlus
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->is_plus) {
            return response()->json([
                'message' => 'This feature requires Plus.'
            ], 402);
        }
        return $next($request);
    }
}
