<?php
// app/Http/Controllers/Customer/CustomerDashboardController.php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomerDashboardController extends Controller
{
    // ─── GET /api/customer/dashboard ──────────────────────────────────────────
    // Mengembalikan:
    //   - stats         : pesanan_aktif, selesai, pengeluaran_bulan_ini
    //   - active_orders : pesanan yang sedang diproses (belum Selesai/Dibatalkan)
    //   - recent_history: 6 riwayat terakhir yang sudah Selesai
    public function index(Request $request)
    {
        $user = $request->user();

        // ── Stats ─────────────────────────────────────────────────────────────
        $pesananAktif = Order::where('user_id', $user->id)
            ->whereNotIn('status', ['Selesai', 'Dibatalkan'])
            ->count();

        $selesai = Order::where('user_id', $user->id)
            ->where('status', 'Selesai')
            ->count();

        $pengeluaranBulanIni = Order::where('user_id', $user->id)
            ->where('status', 'Selesai')
            ->whereYear('order_date', now()->year)
            ->whereMonth('order_date', now()->month)
            ->sum('total_price');

        // ── Pesanan aktif (sedang diproses) ───────────────────────────────────
        $activeOrders = Order::with(['service'])
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Selesai', 'Dibatalkan'])
            ->orderByDesc('order_date')
            ->get()
            ->map(fn($o) => $this->formatActiveOrder($o));

        // ── Riwayat 6 terakhir ────────────────────────────────────────────────
        $recentHistory = Order::with(['service'])
            ->where('user_id', $user->id)
            ->where('status', 'Selesai')
            ->orderByDesc('order_date')
            ->limit(6)
            ->get()
            ->map(fn($o) => $this->formatHistory($o));

        return response()->json([
            'success' => true,
            'stats'   => [
                'pesanan_aktif'         => $pesananAktif,
                'selesai'               => $selesai,
                'pengeluaran_bulan_ini' => (int) $pengeluaranBulanIni,
            ],
            'active_orders'  => $activeOrders,
            'recent_history' => $recentHistory,
        ]);
    }

    // ─── FORMAT ACTIVE ORDER ───────────────────────────────────────────────────
    // Sesuai dengan OrderCard di Dashboard.jsx:
    //   nota, berat, layanan, totalBayar, estimasi, activeStep, timeline
    private function formatActiveOrder(Order $order): array
    {
        // ── Mapping status → activeStep (0-3) ─────────────────────────────────
        // STEPS di frontend: 0=Diterima, 1=Dicuci, 2=Disetrika/Pilah, 3=Siap Diambil
        $stepMap = [
            'Sedang Dicuci'  => 1,
            'Sedang Di Pilah'=> 2,
            'Siap Diambil'   => 3,
        ];
        $activeStep = $stepMap[$order->status] ?? 0;

        // ── Timeline labels ───────────────────────────────────────────────────
        $timeline = is_array($order->timeline) ? $order->timeline : [null, null, null, null];
        while (count($timeline) < 4) $timeline[] = null;

        $timelineFormatted = collect([
            'Sedang Di cuci',
            'Sedang Di Pilah',
            'Siap Di ambil',
            'Selesai',
        ])->map(function ($label, $i) use ($timeline) {
            $raw   = $timeline[$i] ?? null;
            $parts = $raw ? explode("\n", $raw, 2) : [null, null];
            return [
                'label' => $label,
                'done'  => $raw !== null,
                'waktu' => $parts[1] ?? null,
            ];
        });

        return [
            'id'         => $order->id,
            'nota'       => $order->nota,
            'berat'      => $order->weight . 'Kg',
            'layanan'    => $order->service->name ?? '-',
            'totalBayar' => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'estimasi'   => Carbon::parse($order->estimated_date)
                                ->locale('id')->isoFormat('D MMMM, HH.mm') . ' WIB',
            'activeStep' => $activeStep,
            'status'     => $order->status,
            'timeline'   => $timelineFormatted,
        ];
    }

    // ─── FORMAT HISTORY ────────────────────────────────────────────────────────
    // Sesuai tabel riwayat di Dashboard.jsx:
    //   tanggal, nota, layanan, total, status
    private function formatHistory(Order $order): array
    {
        return [
            'tanggal' => Carbon::parse($order->order_date)
                            ->locale('id')->isoFormat('D MMM'),
            'nota'    => $order->nota,
            'layanan' => $order->service->name ?? '-',
            'total'   => 'Rp' . number_format($order->total_price, 0, ',', '.'),
            'status'  => $order->status,
        ];
    }
}