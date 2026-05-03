<?php
// app/Http/Controllers/Admin/PriceSettingController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\Request;

class PriceSettingController extends Controller
{
    // ─── GET /api/admin/prices ────────────────────────────────────────────────
    public function index()
    {
        $kiloan = Service::kiloan()->active()->orderBy('id')
            ->get()->map(fn($s) => $this->fmt($s));

        $addon = Service::addon()->active()->orderBy('id')
            ->get()->map(fn($s) => $this->fmt($s));

        $maxBerat = (int) Setting::getValue('max_berat_per_nota', 7);

        return response()->json([
            'success'   => true,
            'kiloan'    => $kiloan,
            'addon'     => $addon,
            'max_berat' => $maxBerat,
        ]);
    }

    // ─── POST /api/admin/prices ───────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'  => 'required|string|max:100',
            'harga' => 'required|integer|min:0',
            'type'  => 'required|in:kiloan,addon',
        ]);

        $service = Service::create([
            'name'      => $data['nama'],
            'price'     => $data['harga'],
            'type'      => $data['type'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil ditambahkan',
            'data'    => $this->fmt($service),
        ], 201);
    }

    // ─── PUT /api/admin/prices/{id} ───────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $service = Service::withTrashed()->findOrFail($id);

        $data = $request->validate([
            'nama'  => 'sometimes|string|max:100',
            'harga' => 'sometimes|integer|min:0',
        ]);

        $service->update([
            'name'  => $data['nama']  ?? $service->name,
            'price' => $data['harga'] ?? $service->price,
        ]);

        if ($service->trashed()) {
            $service->restore();
            $service->update(['is_active' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Layanan berhasil diperbarui',
            'data'    => $this->fmt($service->fresh()),
        ]);
    }

    // ─── DELETE /api/admin/prices/{id} — SOFT DELETE ──────────────────────────
    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => false]);
        $service->delete(); // soft delete → isi deleted_at

        return response()->json([
            'success' => true,
            'message' => "{$service->name} berhasil dihapus",
        ]);
    }

    // ─── GET /api/admin/prices/trash ─────────────────────────────────────────
    public function trash()
    {
        $trashed = Service::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn($s) => array_merge($this->fmt($s), [
                'deleted_at_label' => $s->deleted_at->format('d M Y H:i'),
            ]));

        return response()->json(['success' => true, 'data' => $trashed]);
    }

    // ─── POST /api/admin/prices/{id}/restore ─────────────────────────────────
    public function restore($id)
    {
        $service = Service::onlyTrashed()->findOrFail($id);
        $service->restore();
        $service->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => "{$service->name} berhasil dipulihkan",
            'data'    => $this->fmt($service->fresh()),
        ]);
    }

    // ─── DELETE /api/admin/prices/{id}/force — HARD DELETE ───────────────────
    public function forceDelete($id)
    {
        $service = Service::onlyTrashed()->findOrFail($id);

        if ($service->orderItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat dihapus permanen, layanan masih digunakan oleh data order.',
            ], 422);
        }

        $service->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Layanan dihapus permanen',
        ]);
    }

    // ─── PUT /api/admin/prices/settings/max-berat ─────────────────────────────
    public function updateMaxBerat(Request $request)
    {
        $data = $request->validate([
            'max_berat' => 'required|integer|min:1|max:999',
        ]);

        Setting::setValue('max_berat_per_nota', $data['max_berat']);

        return response()->json([
            'success'   => true,
            'message'   => "Maksimal berat diperbarui menjadi {$data['max_berat']} Kg",
            'max_berat' => $data['max_berat'],
        ]);
    }

    // ─── HELPER ───────────────────────────────────────────────────────────────
    private function fmt(Service $s): array
    {
        return [
            'id'        => $s->id,
            'nama'      => $s->name,
            'harga'     => (int) $s->price,
            'type'      => $s->type,
            'is_active' => $s->is_active,
        ];
    }
}