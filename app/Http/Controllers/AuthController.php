<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $request->validate([
            'username'         => 'required|string|unique:users,name',
            'no_hp'            => 'required|string|unique:users,phone',
            'alamat'           => 'required|string',
            'password'         => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->username,
            'phone'    => $request->no_hp,
            'address'  => $request->alamat,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil',
            'token'   => $token,
            'user'    => $user,
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('name', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    // Send OTP (simulasi - simpan ke cache)
    public function sendOtp(Request $request)
    {
        $request->validate(['no_hp' => 'required|string']);

        $user = User::where('phone', $request->no_hp)->first();

        if (!$user) {
            return response()->json(['message' => 'Nomor HP tidak ditemukan'], 404);
        }

        $otp = rand(100000, 999999);
        Cache::put('otp_' . $request->no_hp, $otp, now()->addMinutes(5));

        // Di production: kirim OTP via SMS (Twilio, etc.)
        // Untuk development, return OTP di response
        return response()->json([
            'message' => 'OTP telah dikirim',
            'otp'     => $otp, // Hapus baris ini di production!
        ]);
    }

    // Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'no_hp' => 'required|string',
            'otp'   => 'required|string',
        ]);

        $cachedOtp = Cache::get('otp_' . $request->no_hp);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'OTP tidak valid atau sudah kadaluarsa'], 422);
        }

        // Simpan flag bahwa OTP sudah diverifikasi
        Cache::put('otp_verified_' . $request->no_hp, true, now()->addMinutes(10));
        Cache::forget('otp_' . $request->no_hp);

        return response()->json(['message' => 'OTP berhasil diverifikasi']);
    }

    // Reset Password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'no_hp'    => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $verified = Cache::get('otp_verified_' . $request->no_hp);

        if (!$verified) {
            return response()->json(['message' => 'Harap verifikasi OTP terlebih dahulu'], 403);
        }

        $user = User::where('phone', $request->no_hp)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        Cache::forget('otp_verified_' . $request->no_hp);

        return response()->json(['message' => 'Password berhasil diperbarui']);
    }
}
