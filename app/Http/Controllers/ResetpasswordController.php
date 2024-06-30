<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Password_resets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request)
    {
        $token = $request->route('token');
        return view('auth.reset-password', ['token' => $token]);
    }

    public function reset(Request $request)
    {
        $validatedData = $request->validate([
            'password' => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ], [
            'required' => 'Kolom :attribute tidak boleh kosong.',
            'min' => [
                'string' => 'Kolom :attribute minimal harus :min karakter.',
            ],
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
        ]);

        $token = $request->route('token');
        $reset = Password_resets::where('token', $token)->first();

        if (!$reset) {
            return back()->withErrors(['token' => 'Token tidak valid.']);
        }

        $user = User::where('email', $reset->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Email tidak ditemukan.']);
        }

        $user->forceFill([
            'password' => Hash::make($validatedData['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Hapus token reset password yang sudah digunakan
        $reset->delete();

        return redirect()->route('login')->with('status', 'Password berhasil direset.');
    }
}
