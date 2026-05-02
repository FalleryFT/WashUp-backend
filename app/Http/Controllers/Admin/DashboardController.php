<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────────
    // GET /api/admin/dashboard
    // Mengembalikan: stats cards, grafik mingguan, 5 transaksi terbaru
    // ──────────────────────────────────────────────────────────────────────────
    public function index()
{
    $today = Carbon::today();
    $startOfWeek = Carbon::today()->subDays(6); // 7 hari terakhir (termasuk hari ini)

    // ── Status Cards ──────────────────────────────────────────────────────
    $stats = [
        // Pesanan yang masuk hari ini
        'total_order'    => Order::whereDate('order_date', $today)->count(),
        
        // Semua cucian yang masih aktif (tidak dibatasi tanggal hari ini saja)
        'cucian_proses'  => Order::whereIn('status', ['Order Diterima', 'Sedang Di Pilah', 'Sedang Dicuci'])
                                 ->count(),
        
        // Pesanan yang statusnya berubah jadi 'Selesai' hari ini
        // Asumsi: Kita mengecek 'updated_at' saat status diubah ke Selesai
        'selesai'        => Order::where('status', 'Selesai')
                                 ->whereDate('updated_at', $today)
                                 ->count(),
        
        // Omzet selama 7 hari terakhir (sesuai permintaan "minggu ini")
        'omzet_minggu_ini' => (int) Order::where('status', '!=', 'Dibatalkan')
                                     ->whereBetween('order_date', [$startOfWeek, Carbon::now()])
                                     ->sum('total_price'),
    ];

    // ── Grafik Pendapatan 7 Hari Terakhir ─────────────────────────────────
    // (Kode grafik sudah benar, tetap gunakan koleksi range 6-0)
    $weeklyChart = collect(range(6, 0))->map(function ($daysAgo) {
        $date  = Carbon::today()->subDays($daysAgo);
        $total = (int) Order::whereDate('order_date', $date)
                            ->where('status', '!=', 'Dibatalkan')
                            ->sum('total_price');
        return [
            'day'   => $date->locale('id')->isoFormat('ddd'),
            'date'  => $date->toDateString(),
            'value' => $total,
        ];
    })->values();

    $recentOrders = Order::with(['service', 'items.service'])
        ->latest('order_date')
        ->take(5)
        ->get()
        ->map(fn ($o) => self::formatOrder($o));

    return response()->json([
        'success'       => true,
        'stats'         => $stats,
        'weekly_chart'  => $weeklyChart,
        'recent_orders' => $recentOrders,
    ]);
}

    // ──────────────────────────────────────────────────────────────────────────
    // FORMAT ORDER — struktur sama dengan OrderList supaya komponen bisa reuse
    // ──────────────────────────────────────────────────────────────────────────
    public static function formatOrder(Order $o): array
    {
        return [
            'id'         => $o->id,
            'nota'       => $o->nota,
            'nama'       => $o->customer_name,
            'tipe'       => $o->customer_type === 'member' ? 'Member' : 'Non-Member',
            'berat'      => $o->weight . 'Kg',
            'tgl'        => Carbon::parse($o->order_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'estimasi'   => Carbon::parse($o->estimated_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'status'     => $o->status,
            'layanan'    => $o->service->name ?? '-',
            'totalHarga' => 'Rp ' . number_format($o->total_price, 0, ',', '.'),
            'timeline'   => $o->timeline ?? [null, null, null, null],
            'items'      => $o->items->map(fn ($item) => [
                'item'   => $item->item_name,
                'jumlah' => $item->unit === 'kg'
                    ? $item->quantity . 'kg'
                    : (int) $item->quantity . 'x',
                'harga'  => 'Rp' . number_format($item->unit_price, 0, ',', '.'),
                'sub'    => 'Rp' . number_format($item->subtotal,   0, ',', '.'),
            ])->toArray(),
        ];
    }
}