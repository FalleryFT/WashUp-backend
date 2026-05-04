<?php
// app/Http/Controllers/Customer/CustomerHistoryController.php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomerHistoryController extends Controller
{
    // ─── Mapping nama bulan Indonesia → angka ─────────────────────────────────
    // Sesuai array MONTHS di History.jsx
    private const BULAN_MAP = [
        'januari'   => 1,  'februari'  => 2,  'maret'     => 3,
        'april'     => 4,  'mei'       => 5,  'juni'      => 6,
        'juli'      => 7,  'agustus'   => 8,  'september' => 9,
        'oktober'   => 10, 'november'  => 11, 'desember'  => 12,
    ];

    // ─── Mapping status → activeStep (4 step di TIMELINE_STEPS History.jsx) ──
    // Step: 0=Order Diterima, 1=Sedang Dipilah, 2=Sedang Dicuci, 3=Siap Diambil
    private const STEP_MAP = [
        'Order Diterima'  => 0,
        'Sedang Di Pilah' => 1,  // ejaan DB
        'Sedang Dipilah'  => 1,  // ejaan alternatif (toleransi)
        'Sedang Dicuci'   => 2,
        'Siap Diambil'    => 3,
        'Selesai'         => 3,  // semua step done
        'Dibatalkan'      => 0,  // hanya step pertama
    ];

    // ─── GET /api/customer/history ────────────────────────────────────────────
    //
    // Query params (sesuai state di History.jsx):
    //   ?status=Selesai          → filter status, "Semua" = tidak difilter
    //   ?bulan=Januari           → nama bulan Indonesia, "Semua" = tidak difilter
    //   ?sort=Terbaru            → "Terbaru" (desc) | "Terlama" (asc)
    //   ?nota=17081945           → pencarian partial nomor nota
    //   ?page=1                  → halaman (default 1)
    //   ?per_page=5              → item per halaman (default 5 = PER_PAGE)
    //
    // Response:
    // {
    //   "success": true,
    //   "data": [ { id, nota, tanggal, layananUtama, berat, harga, status, detail } ],
    //   "meta": { "total", "per_page", "current_page", "total_pages" }
    // }
    public function index(Request $request)
    {
        $user    = $request->user();
        $perPage = min(max((int)($request->per_page ?? 5), 1), 100);

        $query = Order::with(['service', 'items.service'])
            ->where('user_id', $user->id);

        // ── Filter Status ──────────────────────────────────────────────────────
        if ($request->filled('status') && $request->status !== 'Semua') {
            $query->where('status', $request->status);
        }

        // ── Filter Bulan ───────────────────────────────────────────────────────
        // Frontend kirim nama bulan ("Januari"), bukan angka
        if ($request->filled('bulan') && $request->bulan !== 'Semua') {
            $nomorBulan = self::BULAN_MAP[strtolower($request->bulan)] ?? null;
            if ($nomorBulan) {
                $query->whereMonth('order_date', $nomorBulan);
            }
        }

        // ── Search Nota ────────────────────────────────────────────────────────
        if ($request->filled('nota')) {
            $query->where('nota', 'like', '%' . $request->nota . '%');
        }

        // ── Sort ───────────────────────────────────────────────────────────────
        $dir = ($request->sort === 'Terlama') ? 'asc' : 'desc';
        $query->orderBy('order_date', $dir)->orderBy('id', $dir);

        // ── Paginate ───────────────────────────────────────────────────────────
        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items())
            ->map(fn($o) => $this->formatRow($o));

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'total_pages'  => $paginated->lastPage(),
            ],
        ]);
    }

    // ─── PRIVATE: Format baris tabel ──────────────────────────────────────────
    // Sesuai kolom tabel History.jsx:
    //   id | nota | tanggal | layananUtama | berat | harga | status | detail
    // detail ikut disertakan di sini agar popup tidak perlu request tambahan
    private function formatRow(Order $order): array
    {
        return [
            'id'           => $order->id,
            'nota'         => $order->nota,
            'tanggal'      => Carbon::parse($order->order_date)
                                ->locale('id')->isoFormat('D MMM YYYY'),
            'layananUtama' => $this->buildLayananUtama($order),
            'berat'        => $order->weight . ' Kg',
            'harga'        => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'status'       => $order->status,
            // detail dikirim langsung → popup bisa tampil tanpa request lagi
            'detail'       => $this->formatDetail($order),
        ];
    }

    // ─── PRIVATE: Format detail popup ─────────────────────────────────────────
    // Sesuai yang dipakai DetailPopup + handlePrint di History.jsx:
    //   nota, layanan, tanggalOrder, nama, totalBerat, totalHarga,
    //   estimasi, activeStep, timeline[], items[]
    //   + tipe, tgl (khusus untuk handlePrint)
    private function formatDetail(Order $order): array
    {
        $activeStep = self::STEP_MAP[$order->status] ?? 0;

        // ── Timeline 4 slot ────────────────────────────────────────────────────
        // Label harus sama persis dengan TIMELINE_STEPS di History.jsx
        $defaultTimeline = [
            ['label' => 'Order Diterima',  'tanggal' => '-'],
            ['label' => 'Sedang Dipilah',  'tanggal' => '-'],
            ['label' => 'Sedang Dicuci',   'tanggal' => '-'],
            ['label' => 'Siap Diambil',    'tanggal' => '-'],
        ];

        $stepIndex = [
            'order diterima'  => 0,
            'sedang di pilah' => 1,
            'sedang dipilah'  => 1, // toleransi ejaan alternatif
            'sedang dicuci'   => 2,
            'siap diambil'    => 3,
        ];

        foreach ($order->timeline ?? [] as $step) {
            $key = strtolower(trim($step['label'] ?? ''));
            $idx = $stepIndex[$key] ?? null;
            if ($idx !== null) {
                $defaultTimeline[$idx]['tanggal'] = $step['tanggal'] ?? '-';
            }
        }

        // ── Items ──────────────────────────────────────────────────────────────
        $items = $order->items->map(fn($item) => [
            'item'   => $item->item_name,
            'jumlah' => strtolower($item->unit ?? '') === 'kg'
                ? $item->quantity . ' Kg'
                : $item->quantity . 'x',
            'harga'  => 'Rp ' . number_format($item->unit_price, 0, ',', '.'),
            'sub'    => 'Rp ' . number_format($item->subtotal,   0, ',', '.'),
        ])->toArray();

        $tglFormatted = Carbon::parse($order->order_date)
            ->locale('id')->isoFormat('D MMM YYYY');

        return [
            // ── Untuk DetailPopup ──────────────────────────────────────────────
            'nota'         => $order->nota,
            'layanan'      => $order->service?->name ?? '-',
            'tanggalOrder' => $tglFormatted,
            'nama'         => $order->customer_name ?? $order->user?->name ?? '-',
            'totalBerat'   => $order->weight . ' Kg',
            'totalHarga'   => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'estimasi'     => Carbon::parse($order->estimated_date)
                                ->locale('id')->isoFormat('D MMM YYYY'),
            'activeStep'   => $activeStep,
            'status'       => $order->status,
            'timeline'     => $defaultTimeline,
            'items'        => $items,

            // ── Khusus handlePrint di History.jsx ─────────────────────────────
            // detailItem.tipe  → tipe customer (reguler / member)
            // detailItem.tgl   → tanggal untuk kolom kanan nota cetak
            'tipe'         => ucfirst($order->customer_type ?? 'Reguler'),
            'tgl'          => $tglFormatted,
        ];
    }

    // ─── PRIVATE: Ringkasan layanan untuk kolom tabel ─────────────────────────
    // Contoh: "Kiloan 7 Kg + Bedcover 1x" atau "Kiloan 7 Kg + 2 lainnya"
    private function buildLayananUtama(Order $order): string
    {
        if ($order->items->isEmpty()) {
            return $order->service?->name ?? '-';
        }

        $parts = $order->items->map(function ($item) {
            $jumlah = strtolower($item->unit ?? '') === 'kg'
                ? $item->quantity . ' Kg'
                : $item->quantity . 'x';
            return $item->item_name . ' ' . $jumlah;
        });

        $shown     = $parts->take(2)->implode(' + ');
        $remaining = $parts->count() - 2;

        return $remaining > 0
            ? $shown . ' + ' . $remaining . ' lainnya'
            : $shown;
    }
}