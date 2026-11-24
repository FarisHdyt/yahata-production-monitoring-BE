<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMinimumRole
{
    public function handle(Request $request, Closure $next, string $minimum): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasMinimumRole($minimum)) {
            return response()->json(['message' => 'Anda tidak memiliki akses untuk aksi ini.'], 403);
        }

        return $next($request);
    }
}