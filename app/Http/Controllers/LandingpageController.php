<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Service;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/landing/services
    // Mengembalikan daftar layanan aktif dikelompokkan: kiloan & satuan
    // ─────────────────────────────────────────────────────────────────────────
    public function services()
    {
        $services = Service::active()->orderBy('name')->get();

        $kiloan = $services->where('type', 'kiloan')->values()->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'price' => (float) $s->price,
            'type'  => $s->type,
        ]);

        // Semua type selain kiloan (addon, satuan, dll) masuk ke grup satuan
        $satuan = $services->whereNotIn('type', ['kiloan'])->values()->map(fn($s) => [
            'id'    => $s->id,
            'name'  => $s->name,
            'price' => (float) $s->price,
            'type'  => $s->type,
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'kiloan' => $kiloan,
                'satuan' => $satuan,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/landing/track?nota=NOTA-XXXX
    // Mencari pesanan berdasarkan nomor nota (publik, tanpa autentikasi)
    // ─────────────────────────────────────────────────────────────────────────
    public function track(Request $request)
    {
        $request->validate([
            'nota' => 'required|string|max:100',
        ]);

        $order = Order::with(['items', 'service'])
            ->where('nota', trim($request->nota))
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor nota tidak ditemukan. Periksa kembali nota Anda.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($order),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper: format data order untuk response landing page
    // FIXED: timeline di-parse dari "Status\nTanggal" → {status, date}
    //        total_price & harga item dikirim sebagai float (bukan string)
    // ─────────────────────────────────────────────────────────────────────────
    private function formatOrder(Order $order): array
    {
        // Parse timeline: filter null, pecah string "Status\nTanggal" → object
        $timeline = collect($order->timeline ?? [])
            ->filter()
            ->map(function ($t) {
                if (! $t) return null;
                $parts = explode("\n", $t, 2);
                return [
                    'status' => trim($parts[0] ?? ''),
                    'date'   => trim($parts[1] ?? ''),
                ];
            })
            ->filter()
            ->values();

        return [
            'id'             => $order->id,
            'nota'           => $order->nota,
            'customer_name'  => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_type'  => $order->customer_type,
            'weight'         => $order->weight,

            // Format tanggal Indonesia: "12 Januari 2026"
            'order_date'     => $order->order_date
                                    ? $order->order_date->translatedFormat('d F Y')
                                    : null,
            'estimated_date' => $order->estimated_date
                                    ? $order->estimated_date->translatedFormat('d F Y')
                                    : null,

            'status'         => $order->status,

            // Kirim sebagai float agar rupiah() di JSX tidak crash
            'total_price'    => (float) $order->total_price,

            // Info layanan utama
            'service'        => $order->service?->name,
            'service_type'   => $order->service?->type,

            // Item cucian satuan (untuk tabel di modal)
            'items' => $order->items->map(fn($item) => [
                'item_name'  => $item->item_name,
                'quantity'   => floatval($item->quantity),
                'unit'       => $item->unit,
                'unit_price' => (float) $item->unit_price,
                'subtotal'   => (float) $item->subtotal,
            ]),

            // Timeline sudah dalam format [{status, date}, ...]
            'timeline' => $timeline,
        ];
    }
}