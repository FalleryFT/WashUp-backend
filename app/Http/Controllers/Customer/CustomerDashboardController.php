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

        // ── Pesanan aktif ─────────────────────────────────────────────────────
        $activeOrders = Order::with(['service'])
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['Selesai', 'Dibatalkan'])
            ->orderByDesc('order_date')
            ->get()
            ->map(fn($o) => $this->formatActiveOrder($o));

        // ── Riwayat 8 terakhir (limit disesuaikan dengan slice(0,8) di frontend)
        $recentHistory = Order::with(['service'])
            ->where('user_id', $user->id)
            ->where('status', 'Selesai')
            ->orderByDesc('order_date')
            ->limit(8)
            ->get()
            ->map(fn($o) => $this->formatHistory($o));

        return response()->json([
            'success'        => true,
            'stats'          => [
                'pesanan_aktif'         => $pesananAktif,
                'selesai'               => $selesai,
                'pengeluaran_bulan_ini' => (int) $pengeluaranBulanIni,
            ],
            'active_orders'  => $activeOrders,
            'recent_history' => $recentHistory,
        ]);
    }

    // ─── FORMAT ACTIVE ORDER ───────────────────────────────────────────────────
    // Sesuai STEPS di Dashboard.jsx:
    //   0 = Order Diterima  (ClipboardList)
    //   1 = Sedang Di Pilah (Layers)
    //   2 = Sedang Dicuci   (WashingMachine)
    //   3 = Siap Diambil    (PackageCheck)
    private function formatActiveOrder(Order $order): array
    {
        // Mapping status → activeStep (urutan harus sama persis dengan STEPS frontend)
        $stepMap = [
            'Order Diterima'  => 0,
            'Sedang Di Pilah' => 1,
            'Sedang Dicuci'   => 2,
            'Siap Diambil'    => 3,
        ];
        $activeStep = $stepMap[$order->status] ?? 0;

        // Timeline: 4 slot sesuai 4 step di frontend
        // Label harus cocok dengan key STEPS[i].key di Track.jsx
        $defaultTimeline = [
            ['label' => 'Order Diterima',  'tanggal' => '–'],
            ['label' => 'Sedang Di Pilah', 'tanggal' => '–'],
            ['label' => 'Sedang Dicuci',   'tanggal' => '–'],
            ['label' => 'Siap Diambil',    'tanggal' => '–'],
        ];

        $stepIndex = [
            'order diterima'  => 0,
            'sedang di pilah' => 1,
            'sedang dicuci'   => 2,
            'siap diambil'    => 3,
        ];

        foreach ($order->timeline ?? [] as $step) {
            $key = strtolower(trim($step['label'] ?? ''));
            $idx = $stepIndex[$key] ?? null;
            if ($idx !== null) {
                $defaultTimeline[$idx]['tanggal'] = $step['tanggal'] ?? '–';
            }
        }

        return [
            'id'         => $order->id,
            'nota'       => $order->nota,
            'berat'      => $order->weight . ' Kg',
            'layanan'    => $order->service->name ?? '-',
            'totalBayar' => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'estimasi'   => Carbon::parse($order->estimated_date)
                                ->locale('id')->isoFormat('D MMMM YYYY'),
            'activeStep' => $activeStep,
            'status'     => $order->status,
            'timeline'   => $defaultTimeline,
        ];
    }

    // ─── FORMAT HISTORY ────────────────────────────────────────────────────────
    private function formatHistory(Order $order): array
    {
        return [
            'tanggal' => Carbon::parse($order->order_date)
                            ->locale('id')->isoFormat('D MMM'),
            'nota'    => $order->nota,
            'layanan' => $order->service->name ?? '-',
            'total'   => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'status'  => $order->status,
        ];
    }
}