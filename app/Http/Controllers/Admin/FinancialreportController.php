<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

// Export libraries
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FinancialReportExport;

class FinancialReportController extends Controller
{
    // ─── GET /api/admin/reports ───────────────────────────────────────────────
    // Query params:
    //   month (int 1-12) — default: bulan ini
    //   year  (int)      — default: tahun ini
    //
    // Response:
    //   stats        : total_pendapatan, jumlah_pesanan, rata_rata, persen_growth
    //   chart_data   : array 4 minggu { label, value }
    //   transactions : semua transaksi selesai dalam bulan tersebut
    public function index(Request $request)
    {
        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);

        // ── Transaksi selesai bulan ini ──────────────────────────────────────
        $orders = Order::with(['service'])
            ->whereYear('order_date', $year)
            ->whereMonth('order_date', $month)
            ->whereNotIn('status', ['Dibatalkan'])
            ->orderBy('order_date')
            ->get();

        // ── Stats ─────────────────────────────────────────────────────────────
        $totalPendapatan = $orders->sum('total_price');
        $jumlahPesanan   = $orders->count();
        $rataRata        = $jumlahPesanan > 0
            ? (int) round($totalPendapatan / $jumlahPesanan)
            : 0;

        // Growth: bandingkan dengan bulan sebelumnya
        $prevDate   = Carbon::create($year, $month, 1)->subMonth();
        $prevTotal  = Order::whereYear('order_date', $prevDate->year)
            ->whereMonth('order_date', $prevDate->month)
            ->whereNotIn('status', ['Dibatalkan'])
            ->sum('total_price');
        $growth = $prevTotal > 0
            ? round((($totalPendapatan - $prevTotal) / $prevTotal) * 100, 1)
            : null;

        // ── Chart: 4 minggu dalam bulan ───────────────────────────────────────
        $daysInMonth = Carbon::create($year, $month)->daysInMonth;
        $weeks = [
            ['label' => "1-7",                          'from' => 1,  'to' => 7],
            ['label' => "8-14",                         'from' => 8,  'to' => 14],
            ['label' => "15-21",                        'from' => 15, 'to' => 21],
            ['label' => "22-{$daysInMonth}",            'from' => 22, 'to' => $daysInMonth],
        ];

        $chartData = collect($weeks)->map(function ($week) use ($orders) {
            $value = $orders->filter(function ($o) use ($week) {
                $day = (int) Carbon::parse($o->order_date)->format('d');
                return $day >= $week['from'] && $day <= $week['to'];
            })->sum('total_price');

            return ['label' => $week['label'], 'value' => (int) $value];
        })->values();

        // ── Format transaksi ──────────────────────────────────────────────────
        $transactions = $orders->map(fn($o) => $this->formatTransaction($o));

        return response()->json([
            'success' => true,
            'meta'    => ['month' => $month, 'year' => $year],
            'stats'   => [
                'total_pendapatan' => $totalPendapatan,
                'jumlah_pesanan'   => $jumlahPesanan,
                'rata_rata'        => $rataRata,
                'growth'           => $growth, // null = tidak ada data bulan lalu
            ],
            'chart_data'   => $chartData,
            'transactions' => $transactions,
        ]);
    }

    // ─── GET /api/admin/reports/export?format=pdf|excel&month=&year= ─────────
    public function export(Request $request)
    {
        $format = strtolower($request->get('format', 'pdf'));
        $month  = (int) $request->get('month', now()->month);
        $year   = (int) $request->get('year',  now()->year);

        // Ambil data yang sama dengan index()
        $orders = Order::with(['service'])
            ->whereYear('order_date', $year)
            ->whereMonth('order_date', $month)
            ->whereNotIn('status', ['Dibatalkan'])
            ->orderBy('order_date')
            ->get();

        $transactions    = $orders->map(fn($o) => $this->formatTransaction($o));
        $totalPendapatan = $orders->sum('total_price');
        $jumlahPesanan   = $orders->count();
        $rataRata        = $jumlahPesanan > 0
            ? (int) round($totalPendapatan / $jumlahPesanan)
            : 0;

        $monthName = Carbon::create($year, $month)->locale('id')->isoFormat('MMMM');
        $filename  = "laporan-keuangan-{$monthName}-{$year}";

        $data = [
            'transactions'    => $transactions,
            'totalPendapatan' => $totalPendapatan,
            'jumlahPesanan'   => $jumlahPesanan,
            'rataRata'        => $rataRata,
            'monthName'       => $monthName,
            'year'            => $year,
        ];

        // ── Export PDF ────────────────────────────────────────────────────────
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.financial-report-pdf', $data)
                ->setPaper('a4', 'portrait');

            return $pdf->download("{$filename}.pdf");
        }

        // ── Export Excel ──────────────────────────────────────────────────────
        if ($format === 'excel') {
            return Excel::download(
                new FinancialReportExport($data),
                "{$filename}.xlsx"
            );
        }

        return response()->json(['message' => 'Format tidak valid. Gunakan: pdf atau excel'], 422);
    }

    // ─── FORMAT HELPER ────────────────────────────────────────────────────────
    private function formatTransaction(Order $order): array
    {
        return [
            'id'        => $order->id,
            'tanggal'   => Carbon::parse($order->order_date)
                ->locale('id')->isoFormat('D MMMM YYYY'),
            'nota'      => $order->nota,
            'pelanggan' => $order->customer_name,
            'layanan'   => $order->service->name ?? '-',
            'nominal'   => (int) $order->total_price,
        ];
    }
}