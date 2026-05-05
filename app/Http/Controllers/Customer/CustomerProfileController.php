<?php
// app/Http/Controllers/Customer/CustomerProfileController.php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class CustomerProfileController extends Controller
{
    // ─── GET /api/customer/profile ────────────────────────────────────────────
    // Mengembalikan data profil customer yang sedang login
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => $this->formatProfile($user),
        ]);
    }

    // ─── PUT /api/customer/profile ────────────────────────────────────────────
    // Update nama, no_hp, alamat
    // ID pelanggan (user->id) tidak bisa diubah — tidak ada di $fillable validasi
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nama'   => ['required', 'string', 'max:100'],
            'no_hp'  => ['required', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:500'],
        ], [
            'nama.required'  => 'Nama lengkap wajib diisi.',
            'no_hp.required' => 'Nomor HP wajib diisi.',
        ]);

        $user->update([
            'name'    => $validated['nama'],
            'phone'   => $validated['no_hp'],
            'address' => $validated['alamat'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data'    => $this->formatProfile($user->fresh()),
        ]);
    }

    // ─── PRIVATE: Format profil ───────────────────────────────────────────────
    // Sesuai field yang ditampilkan di Profile.jsx:
    //   id, nama, noHp, alamat, memberSejak
    private function formatProfile($user): array
    {
        return [
            'id'          => $user->id,
            'idFormatted' => 'ID-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
            'nama'        => $user->name ?? '',
            'email'       => $user->email ?? '',
            'noHp'        => $user->phone ?? '',
            'alamat'      => $user->address ?? '',
            'memberSejak' => $user->created_at
                ? Carbon::parse($user->created_at)->locale('id')->isoFormat('D MMM YYYY')
                : '-',
        ];
    }
}