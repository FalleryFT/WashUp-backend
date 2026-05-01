<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Tampilkan data user yang memiliki role 'customer'
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $customers = User::where('role', 'customer')
            ->when($search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('id', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar data customer berhasil diambil',
            'data'    => $customers
        ], 200);
    }

    /**
     * Tambahkan customer baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:users,phone',
            'address' => 'nullable|string',
        ]);

        // Sesuai dengan data dummy, password default diset 'user123'
        $validated['password'] = Hash::make('user123');
        $validated['role']     = 'customer';

        $customer = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer berhasil ditambahkan',
            'data'    => $customer
        ], 201);
    }

    /**
     * Update data customer
     */
    public function update(Request $request, $id)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'required|string|max:20|unique:users,phone,' . $id,
            'address' => 'nullable|string',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data customer berhasil diperbarui',
            'data'    => $customer
        ], 200);
    }

    /**
     * Soft Delete data customer
     */
    public function destroy($id)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);
        
        // Ini akan menjalankan Soft Delete karena model menggunakan Trait SoftDeletes
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data customer berhasil dihapus (Soft Delete)'
        ], 200);
    }
}