<?php
// app/Http/Controllers/Customer/CustomerLacakController.php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerLacakController extends Controller
{
    /**
     * GET /api/customer/orders
     *
     * Mengembalikan pesanan aktif milik customer (exclude Selesai & Dibatalkan
     * sudah difilter di frontend; controller tetap kirim semua agar fleksibel).
     */
    public function index()
    {
        $orders = Auth::user()
            ->orders()
            ->with(['service', 'items.service'])
            ->latest('order_date')
            ->get()
            ->map(fn ($order) => $this->formatOrder($order));

        return response()->json(['data' => $orders]);
    }

    /**
     * GET /api/customer/orders/{nota}
     */
    public function show(string $nota)
    {
        $order = Auth::user()
            ->orders()
            ->with(['service', 'items.service'])
            ->where('nota', $nota)
            ->firstOrFail();

        return response()->json(['data' => $this->formatOrder($order)]);
    }

    // ─── Private Helper ────────────────────────────────────────────────────────

    private function formatOrder($order): array
    {
        // Mapping label timeline (case-insensitive) → index slot 0-3
        // Harus cocok dengan urutan STEPS di frontend
        $stepMap = [
            'order diterima'  => 0,
            'sedang di pilah' => 1,
            'sedang dicuci'   => 2,
            'siap diambil'    => 3,
        ];

        // Default 4 slot timeline; tanggal diisi dari kolom JSON DB
        $defaultSteps = [
            ['label' => 'Order Diterima',  'tanggal' => '–'],
            ['label' => 'Sedang Di Pilah', 'tanggal' => '–'],
            ['label' => 'Sedang Dicuci',   'tanggal' => '–'],
            ['label' => 'Siap Diambil',    'tanggal' => '–'],
        ];

        foreach ($order->timeline ?? [] as $step) {
            $key = strtolower(trim($step['label'] ?? ''));
            $idx = $stepMap[$key] ?? null;
            if ($idx !== null) {
                $defaultSteps[$idx]['tanggal'] = $step['tanggal'] ?? '–';
            }
        }

        return [
            'nota'         => $order->nota,

            // status dikirim RAW — frontend yang mapping ke activeStep & badge warna
            // Nilai: "Order Diterima" | "Sedang Di Pilah" | "Sedang Dicuci" |
            //        "Siap Diambil"   | "Selesai"          | "Dibatalkan"
            'status'       => $order->status,

            // order_date RAW (Y-m-d) untuk sorting di frontend
            'order_date'   => $order->order_date?->toDateString(),

            'layanan'      => $order->service?->name ?? '–',
            'tanggalOrder' => $order->order_date?->translatedFormat('j F Y') ?? '–',
            'totalBerat'   => $order->weight ? $order->weight . ' Kg' : '–',
            'totalHarga'   => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'estimasi'     => $order->estimated_date?->translatedFormat('j F Y') ?? '–',
            'timeline'     => $defaultSteps,
            'items'        => $order->items->map(fn ($item) => [
                'item'   => $item->item_name,
                'jumlah' => strtolower($item->unit ?? '') === 'kg'
                    ? $item->quantity . ' Kg'
                    : $item->quantity . 'x',
                'harga'  => 'Rp ' . number_format($item->unit_price, 0, ',', '.'),
                'sub'    => 'Rp ' . number_format($item->subtotal,   0, ',', '.'),
            ])->toArray(),
        ];
    }
}