<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Check which type of user this is
        $userType = null;
        if ($user instanceof \App\Models\Pembeli) {
            $userType = 'pembeli';
        } elseif ($user instanceof \App\Models\Penitip) {
            $userType = 'penitip';
        } elseif ($user instanceof \App\Models\Organisasi) {
            $userType = 'organisasi';
        } elseif ($user instanceof \App\Models\Pegawai) {
            $userType = 'pegawai';
            if ($user->ID_JABATAN == 1) {
                $userType = 'owner';
            } elseif ($user->ID_JABATAN == 3) {
                $userType = 'admin';
            } elseif ($user->ID_JABATAN == 2) {
                $userType = 'Customer Service';
            } elseif ($user->ID_JABATAN == 4) {
                $userType = 'gudang';
            } elseif ($user->ID_JABATAN == 5) {
                $userType = 'kurir';
            } elseif ($user->ID_JABATAN == 6) {
                $userType = 'hunter';
            }
        }
        
        foreach ($roles as $role) {
            if ($userType === $role) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}