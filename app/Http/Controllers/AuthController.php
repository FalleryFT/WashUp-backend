<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // ── Register ──────────────────────────────────────────────────────────────
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,name',
            'email'    => 'required|email|unique:users,email',
            'no_hp'    => 'required|string|unique:users,phone',
            'alamat'   => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->username,
            'email'    => $request->email,
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

    // ── Login (username / no HP / email) ──────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $input = $request->username;

        // Cari user berdasarkan name, phone, atau email
        $user = User::where('name', $input)
            ->orWhere('phone', $input)
            ->orWhere('email', $input)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username, No HP, Email, atau password salah.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    // ── Logout ────────────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil']);
    }

    // ── Send OTP via Email ────────────────────────────────────────────────────
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email tidak ditemukan'], 404);
        }

        $otp = rand(100000, 999999);
        Cache::put('otp_' . $request->email, $otp, now()->addMinutes(5));

        // Di production: kirim OTP via email
        // Mail::to($request->email)->send(new OtpMail($otp));

        // Untuk development, return OTP di response
        return response()->json([
            'message' => 'OTP telah dikirim ke email Anda',
            'otp'     => $otp, // Hapus baris ini di production!
        ]);
    }

    // ── Verify OTP ────────────────────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string',
        ]);

        $cachedOtp = Cache::get('otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'OTP tidak valid atau sudah kadaluarsa'], 422);
        }

        Cache::put('otp_verified_' . $request->email, true, now()->addMinutes(10));
        Cache::forget('otp_' . $request->email);

        return response()->json(['message' => 'OTP berhasil diverifikasi']);
    }

    // ── Reset Password ────────────────────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $verified = Cache::get('otp_verified_' . $request->email);

        if (!$verified) {
            return response()->json(['message' => 'Harap verifikasi OTP terlebih dahulu'], 403);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        Cache::forget('otp_verified_' . $request->email);

        return response()->json(['message' => 'Password berhasil diperbarui']);
    }
}