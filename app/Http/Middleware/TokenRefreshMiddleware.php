<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TokenRefreshMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process if the request was successful and user is authenticated
        if ($response->getStatusCode() === 200 && Auth::check()) {
            $user = Auth::user();
            $token = $user->currentAccessToken();
            
            // Check if token is close to expiring (within 5 minutes)
            if ($token && isset($token->expires_at) && $token->expires_at && $token->expires_at->diffInMinutes(now()) <= 5) {
                // Add headers to indicate token needs refresh
                $response->headers->set('X-Token-Refresh-Required', 'true');
                $response->headers->set('X-Token-Expires-At', $token->expires_at->toISOString());
            }
        }

        return $response;
    }
}
