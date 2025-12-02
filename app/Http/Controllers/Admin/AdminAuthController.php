<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        // 1) Validar datos
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2) Intentar autenticación con el guard web
        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // 3) Verificar que sea admin
        if (! $user->is_admin) {
            return response()->json([
                'message' => 'User is not admin',
            ], 403);
        }

        // 4) Opcional: limpiar tokens anteriores
        $user->tokens()->delete();

        // 5) Crear token nuevo
        $token = $user->createToken('admin-panel')->plainTextToken;

        // 6) Devolver token y datos básicos
        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $user->id,
                'email'    => $user->email,
                'name'     => $user->name,
                'is_admin' => (bool) $user->is_admin,
            ],
        ]);
    }
}
