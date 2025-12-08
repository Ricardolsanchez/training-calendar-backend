<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validar datos
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2. Intentar login
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        // 3. Verificar admin
        if (!$user->is_admin) {
            Auth::logout();

            return response()->json([
                'message' => 'Not authorized',
            ], 403);
        }

        // 4. Regenerar sesión
        $request->session()->regenerate();

        // 5. Crear token Sanctum
        $token = $user->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => [
                'id'       => $user->id,
                'email'    => $user->email,
                'name'     => $user->name,
                'is_admin' => (bool) $user->is_admin,
            ],
        ]);
    }
}
