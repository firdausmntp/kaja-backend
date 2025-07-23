<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:pembeli,penjual'
        ]);

        if ($request->role === 'admin') {
            return response()->json([
                'message' => 'Role admin tidak diperbolehkan untuk register.'
            ], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role
        ]);

        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $token = $user->createToken('token')->plainTextToken;

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        // Validasi password lama
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai.',
                'errors' => [
                    'current_password' => ['Password lama tidak sesuai.']
                ]
            ], 422);
        }

        // Update password baru
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Logout dari semua device (hapus semua token)
        $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('token')->plainTextToken;

        return response()->json([
            'message' => 'Password berhasil diubah. Silakan login ulang.',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $request->user()->id,
        ]);

        $user = $request->user();

        // Update data yang dikirim
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirmation' => 'required|in:DELETE,delete',
        ]);

        $user = $request->user();

        // Validasi password untuk konfirmasi
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Password tidak sesuai.',
                'errors' => [
                    'password' => ['Password tidak sesuai.']
                ]
            ], 422);
        }

        // Hapus semua token
        $user->tokens()->delete();

        // Hapus akun (soft delete jika menggunakan SoftDeletes)
        $user->delete();

        return response()->json([
            'message' => 'Akun berhasil dihapus. Terima kasih telah menggunakan layanan kami.'
        ]);
    }
}
