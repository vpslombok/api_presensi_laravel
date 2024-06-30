<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Models\Presensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ValidQrCodes;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use App\Models\Password_resets;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;



class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'password' => 'required',
        ]);

        $user = \App\Models\User::where('nik', $request->nik)->first();

        if (!$user) {
            return response()->json([
                'message' => 'NIK tidak terdaftar'
            ], 404);
        }

        if (!Auth::attempt($request->only('nik', 'password'))) {
            return response()->json([
                'message' => 'Login Gagal'
            ], 401);
        }

        // Hapus semua token yang ada sebelum membuat yang baru
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'name' => $user->name,
                'nik' => $user->nik,
                'email' => $user->email,
                'foto' => $user->foto,
            ],
            'message' => 'Selamat Datang ' . $user->name
        ], 200);
    }

    public function dataPresensi()
    {
        $user = Auth::user();
        $presensi = Presensi::where('user_id', $user->id)->get();
        return response()->json($presensi);
    }

    public function presensi(Request $request)
    {
        $kodeQR = $request->all();

        // Validasi QR Code: Harus ada dalam database dan dibuat dalam waktu kurang dari 5 menit
        $validasiQR = ValidQrCodes::where('code', $kodeQR)
            ->where('created_at', '>', now()->subMinutes(5))
            ->first();

        if (!$validasiQR) {
            return response()->json([
                'message' => 'Kode QR salah atau sudah expired',
                'request_data' => $request->only('code')
            ], 400);
        }

        // Validasi lokasi berdasarkan latitude dan longitude dengan toleransi radius 100 meter
        $setting = Setting::first();
        $earthRadius = 6371000; // dalam meter

        $latFrom = deg2rad($setting->latitude);
        $lonFrom = deg2rad($setting->longitude);
        $latTo = deg2rad($request->latitude);
        $lonTo = deg2rad($request->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $distance = $angle * $earthRadius;

        if ($distance > 100) {
            return response()->json([
                'message' => 'Lokasi tidak sesuai',
                'request_data' => $request->only('latitude', 'longitude')
            ], 400);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak terdaftar'
            ], 401);
        }

        $tanggalSekarang = now()->format('Y-m-d');
        $presensi = Presensi::where('user_id', $user->id)->whereDate('tanggal', $tanggalSekarang)->first();
        $presensiMasuk = Presensi::where('user_id', $user->id)->whereDate('tanggal', $tanggalSekarang)->whereNotNull('waktu_masuk')->pluck('waktu_masuk')->first();
        $presensiKeluar = Presensi::where('user_id', $user->id)->whereDate('tanggal', $tanggalSekarang)->whereNotNull('waktu_keluar')->pluck('waktu_keluar')->first();
        if (!$presensi) {
            $presensi = new Presensi();
            $presensi->user_id = $user->id;
            $presensi->tanggal = now();
            $presensi->waktu_masuk = now();
            $presensi->save();
            return response()->json([
                'message' => 'Presensi masuk berhasil pada ' . now()->format('H:i:s')
            ], 200);
        } else {
            if ($presensi->waktu_keluar) {
                return response()->json([
                    'message' => 'Anda sudah melakukan presensi masuk pada hari ini jam ' . $presensiMasuk . ' dan keluar pada jam ' . $presensiKeluar
                ], 400);
            }
            $presensi->waktu_keluar = now();
            $presensi->save();
            return response()->json([
                'message' => 'Presensi keluar berhasil pada ' . now()->format('H:i:s')
            ], 200);
        }
    }

    public function edit_profile(Request $request)
    {
        $user = Auth::user();
        $oldPassword = $request->input('old_password');
        $newPassword = $request->input('new_password');

        if (Hash::check($oldPassword, $user->password)) {
            $user->password = Hash::make($newPassword);
            $user->save();
            return response()->json([
                'message' => 'Password berhasil diubah'
            ], 200);
        } else {
            return response()->json([
                'message' => 'Password lama tidak sesuai'
            ], 400);
        }
    }

    // Fungsi reset password melalui email API
    public function reset_password(Request $request)
    {
        $validatedData = $request->validate([
            'nik' => 'required|numeric',
        ]);

        $user = User::where('nik', $validatedData['nik'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'NIK tidak ditemukan'
            ], 404);
        }

        Password_resets::where('email', $user->email)->delete();

        $token = Str::random(60);
        Password_resets::create([
            'email' => $user->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        $to = $user->email;
        $subject = 'Reset Password';
        $link = url('/reset-password/' . $token);
        $body = "Klik link ini untuk mereset password: <a href='$link'>$link</a>";

        Mail::send([], [], function ($message) use ($to, $subject, $body) {
            $message->to($to)
                ->subject($subject)
                ->html($body);
        });

        return response()->json([
            'message' => 'Silahkan cek email Anda untuk mereset password',
        ], 200);
    }
}
