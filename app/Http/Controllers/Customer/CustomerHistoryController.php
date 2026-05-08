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
    private const BULAN_MAP = [
        'januari'   => 1,  'februari'  => 2,  'maret'     => 3,
        'april'     => 4,  'mei'       => 5,  'juni'      => 6,
        'juli'      => 7,  'agustus'   => 8,  'september' => 9,
        'oktober'   => 10, 'november'  => 11, 'desember'  => 12,
    ];

    // ─── Mapping status → activeStep ──────────────────────────────────────────
    private const STEP_MAP = [
        'Order Diterima'  => 0,
        'Sedang Di Pilah' => 1,
        'Sedang Dipilah'  => 1,
        'Sedang Dicuci'   => 2,
        'Siap Diambil'    => 3,
        'Selesai'         => 3,
        'Dibatalkan'      => -1, // tidak mengisi timeline
    ];

    // ─── Label 4 step timeline (urut, sesuai TIMELINE_STEPS di History.jsx) ───
    private const TIMELINE_LABELS = [
        0 => 'Order Diterima',
        1 => 'Sedang Dipilah',
        2 => 'Sedang Dicuci',
        3 => 'Siap Diambil',
    ];

    // ─── GET /api/customer/history ────────────────────────────────────────────
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
            'detail'       => $this->formatDetail($order),
        ];
    }

    // ─── PRIVATE: Format detail popup ─────────────────────────────────────────
    private function formatDetail(Order $order): array
    {
        $activeStep = self::STEP_MAP[$order->status] ?? 0;

        // ── Bangun timeline 4 slot ─────────────────────────────────────────────
        // Format: null = belum terjadi | "Label\nTanggal" = sudah terjadi
        // Sama persis dengan struktur yang dipakai Dashboard.jsx
        $timeline = [null, null, null, null];

        if ($order->status !== 'Dibatalkan') {
            // Kumpulkan tanggal yang sudah disimpan di DB (jika ada)
            // DB menyimpan sebagai array [{label, tanggal}] atau [null, "...\n..."]
            $savedDates = $this->extractSavedDates($order->timeline ?? []);

            // Isi slot 0 sampai activeStep
            for ($i = 0; $i <= $activeStep; $i++) {
                $label = self::TIMELINE_LABELS[$i];

                // Prioritas: tanggal dari DB → fallback order_date untuk step 0 → strip
                if (!empty($savedDates[$i])) {
                    $tanggal = $savedDates[$i];
                } elseif ($i === 0) {
                    // Step pertama selalu pakai tanggal order masuk
                    $tanggal = Carbon::parse($order->order_date)
                        ->locale('id')->isoFormat('D MMM YYYY, HH:mm');
                } else {
                    $tanggal = '-';
                }

                // Format: "Label\nTanggal" — identik dengan Dashboard.jsx
                $timeline[$i] = $label . "\n" . $tanggal;
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

            // timeline: array 4 slot [null|"Label\nTanggal", ...]
            // Format identik dengan Dashboard.jsx agar Timeline component bisa reuse
            'timeline'     => $timeline,

            'items'        => $items,
            'tipe'         => ucfirst($order->customer_type ?? 'Reguler'),
            'tgl'          => $tglFormatted,
        ];
    }

    // ─── PRIVATE: Ekstrak tanggal tersimpan dari kolom timeline DB ────────────
    // Mendukung 2 format penyimpanan:
    //   Format A (dari Dashboard/OrderList admin): array of null|"Label\nTanggal"
    //   Format B (lama): array of {label, tanggal}
    private function extractSavedDates(array $rawTimeline): array
    {
        $dates = [];

        $stepIndex = [
            'order diterima'  => 0,
            'order di terima' => 0,
            'sedang di pilah' => 1,
            'sedang dipilah'  => 1,
            'sedang dicuci'   => 2,
            'sedang di cuci'  => 2,
            'siap diambil'    => 3,
            'siap di ambil'   => 3,
        ];

        foreach ($rawTimeline as $idx => $slot) {
            if ($slot === null) continue;

            // Format A: "Label\nTanggal"
            if (is_string($slot)) {
                $parts = explode("\n", $slot, 2);
                $label = strtolower(trim($parts[0] ?? ''));
                $tgl   = trim($parts[1] ?? '');
                $i     = $stepIndex[$label] ?? (is_int($idx) ? $idx : null);
                if ($i !== null && $tgl !== '' && $tgl !== '-') {
                    $dates[$i] = $tgl;
                }
                continue;
            }

            // Format B: {label: "...", tanggal: "..."}
            if (is_array($slot) && isset($slot['tanggal']) && $slot['tanggal'] !== '-') {
                if (is_int($idx)) {
                    $dates[$idx] = $slot['tanggal'];
                } elseif (isset($slot['label'])) {
                    $key = strtolower(trim($slot['label']));
                    $i   = $stepIndex[$key] ?? null;
                    if ($i !== null) $dates[$i] = $slot['tanggal'];
                }
            }
        }

        return $dates;
    }

    // ─── PRIVATE: Ringkasan layanan untuk kolom tabel ─────────────────────────
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