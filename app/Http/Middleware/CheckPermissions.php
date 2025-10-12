<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\HttpResponseTrait;

class CheckPermissions
{
    use HttpResponseTrait;

    public function handle(Request $request, Closure $next, ...$args)
    {
        if (!Auth::check()) {
            return $this->sendErrorOfUnauthorized("Unauthorized");
        }

        $mode = 'all';
        if (isset($args[0]) && in_array(strtolower($args[0]), ['any', 'all'])) {
            $mode = strtolower(array_shift($args));
        }

        $required = collect($args)
            ->flatMap(function ($chunk) {
                return preg_split('/[,\|]/', (string) $chunk, -1, PREG_SPLIT_NO_EMPTY);
            })
            ->map(fn($s) => trim((string) $s))
            ->filter()
            ->unique()
            ->values();

        if ($required->isEmpty()) {
            return $next($request);
        }

        $user = Auth::user();

        $user->loadMissing([
            'roles:id,is_superuser',
            'roles.permissions:id,code',
        ]);

        $isSuperuser = $user->roles->contains(fn($r) => (bool) $r->is_superuser === true);
        if ($isSuperuser) {
            return $next($request);
        }

        $granted = $user->roles
            ->flatMap(fn($r) => $r->permissions)
            ->pluck('code')
            ->filter()
            ->unique()
            ->values();

        $ok = $mode === 'any'
            ? $required->some(fn($code) => $granted->contains($code))
            : $required->every(fn($code) => $granted->contains($code));

        if (!$ok) {
            return $this->sendErrorOfForbidden("You do not have sufficient privileges to perform this action.");

            // return response()->json([
            //     'success'  => false,
            //     'message'  => 'Forbidden: missing required permission(s).',
            //     'required' => $required->all(),
            //     'granted'  => $granted->all(),
            //     'mode'     => $mode,
            // ], 403);
        }

        return $next($request);
    }
}
