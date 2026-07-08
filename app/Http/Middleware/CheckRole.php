<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!in_array($request->user()->role_id, $roles)) {
            return response()->json(['message' => 'Unauthorized Access'], 403);
        }

        return $next($request);
    }
}