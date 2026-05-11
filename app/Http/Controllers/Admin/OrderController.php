<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    // ─── READ ALL ORDERS ───────────────────────────────────────────────────
    public function index(Request $request)
    {
        $query = Order::with(['items', 'service']);

        // 1. Pencarian berdasarkan Nota atau Nama Pelanggan
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nota', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // 2. Filter Tab Status
        if ($request->filled('status') && $request->status !== 'SEMUA') {
            $statusMap = [
                'Order Diterima' => ['Order Diterima'],
                'Sedang DiPilah' => ['Sedang Di Pilah'],
                'Sedang DiCuci'  => ['Sedang Dicuci'],
                'SIAP AMBIL'     => ['Siap Diambil'],
                'SELESAI'        => ['Selesai'],
                'DIBATALKAN'     => ['Dibatalkan'],
            ];
            if (isset($statusMap[$request->status])) {
                $query->whereIn('status', $statusMap[$request->status]);
            }
        }

        // 3. Filter Tipe Pelanggan (Enum: member, non-member)
        if ($request->filled('customer_type') && $request->customer_type !== 'Semua Tipe') {
            $type = strtolower($request->customer_type);
            $query->where('customer_type', $type);
        }

        // 4. Filter Bulan — Default: bulan berjalan jika parameter kosong
        $month = $request->filled('month') ? $request->month : null;

        if ($month && $month !== 'Semua Bulan') {
            // Parameter 'month' dikirimkan dalam format 'YYYY-MM' (contoh: 2026-05)
            $dateParts = explode('-', $month);
            if (count($dateParts) === 2) {
                $query->whereYear('order_date', $dateParts[0])
                      ->whereMonth('order_date', $dateParts[1]);
            }
        } elseif (!$month) {
            // Jika tidak ada parameter month sama sekali → default bulan ini
            $query->whereYear('order_date', Carbon::now()->year)
                  ->whereMonth('order_date', Carbon::now()->month);
        }
        // Jika $month === 'Semua Bulan' → tidak ada filter bulan (tampil semua)

        // 5. Fitur Sort (Terbaru / Terlama)
        if ($request->filled('sort') && $request->sort === 'oldest') {
            $query->orderBy('order_date', 'asc');
        } else {
            $query->orderBy('order_date', 'desc'); // Default terbaru
        }

        $orders = $query->get();

        $formattedOrders = $orders->map(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'success' => true,
            'data' => $formattedOrders
        ]);
    }

    // ─── NEXT STATUS (Maju ke fase berikutnya) ──────────────────────────────
    public function nextStatus($id)
    {
        $order = Order::with(['items', 'service'])->findOrFail($id);
        
        $statusSequence = [
            'Order Diterima',
            'Sedang Di Pilah',
            'Sedang Dicuci',
            'Siap Diambil',
            'Selesai'
        ];
        
        $currentIndex = array_search($order->status, $statusSequence);
        
        if ($currentIndex !== false && $currentIndex < count($statusSequence) - 1) {
            $nextStatus = $statusSequence[$currentIndex + 1];
            $order->status = $nextStatus;

            $timeline = $order->timeline;
            if (!is_array($timeline) || empty($timeline)) {
                $timeline = [null, null, null, null];
            }

            $nowStr = Carbon::now()->format('d M H.i');

            if ($nextStatus === 'Sedang Di Pilah') {
                $timeline[0] = "Order di terima\n" . $order->created_at->format('d M H.i');
                $timeline[1] = "Sedang Di Pilah\n" . $nowStr;
            } elseif ($nextStatus === 'Sedang Dicuci') {
                $timeline[1] = "Sedang Di Pilah\n" . $nowStr;
                $timeline[2] = "Sedang Di cuci\n" . $nowStr;
            } elseif ($nextStatus === 'Siap Diambil') {
                $timeline[2] = "Sedang Di cuci\n" . $nowStr;
                $timeline[3] = "Siap Di ambil\n" . $nowStr;
            } elseif ($nextStatus === 'Selesai') {
                $timeline[3] = "Selesai\n" . $nowStr;
            }

            $order->timeline = $timeline;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Status berhasil diperbarui ke ' . $nextStatus,
                'data' => $this->formatOrder($order)
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Status tidak dapat dilanjutkan lagi.'
        ], 400);
    }

    // ─── PREV STATUS / UNDO (Mundur ke fase sebelumnya) ────────────────────
    public function prevStatus($id)
    {
        $order = Order::with(['items', 'service'])->findOrFail($id);

        $statusSequence = [
            'Order Diterima',
            'Sedang Di Pilah',
            'Sedang Dicuci',
            'Siap Diambil',
            'Selesai'
        ];

        // Tidak bisa undo jika sudah Dibatalkan
        if ($order->status === 'Dibatalkan') {
            return response()->json([
                'success' => false,
                'message' => 'Pesanan yang dibatalkan tidak dapat di-undo.'
            ], 400);
        }

        $currentIndex = array_search($order->status, $statusSequence);

        // Tidak bisa undo jika sudah di status pertama
        if ($currentIndex === false || $currentIndex === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Status sudah berada di tahap pertama, tidak bisa di-undo.'
            ], 400);
        }

        $prevStatus = $statusSequence[$currentIndex - 1];
        $order->status = $prevStatus;

        // Reset timeline: hapus entry pada index currentIndex dan ke atas
        $timeline = $order->timeline;
        if (!is_array($timeline) || empty($timeline)) {
            $timeline = [null, null, null, null];
        }

        // Mapping: index status → index timeline yang perlu dihapus saat undo
        // 'Sedang Di Pilah' (index 1) → timeline[1] di-reset (kembali ke Order Diterima)
        // 'Sedang Dicuci'   (index 2) → timeline[2] di-reset
        // 'Siap Diambil'    (index 3) → timeline[3] di-reset
        // 'Selesai'         (index 4) → timeline[3] tetap (Selesai pakai slot [3] juga)
        if ($currentIndex === 1) {
            // Undo dari Sedang Di Pilah → Order Diterima: reset slot 0 & 1
            $timeline[0] = null;
            $timeline[1] = null;
        } elseif ($currentIndex === 2) {
            // Undo dari Sedang Dicuci → Sedang Di Pilah: reset slot 1 & 2
            $timeline[1] = "Sedang Di Pilah\n" . $order->updated_at->format('d M H.i');
            $timeline[2] = null;
        } elseif ($currentIndex === 3) {
            // Undo dari Siap Diambil → Sedang Dicuci: reset slot 2 & 3
            $timeline[2] = "Sedang Di cuci\n" . $order->updated_at->format('d M H.i');
            $timeline[3] = null;
        } elseif ($currentIndex === 4) {
            // Undo dari Selesai → Siap Diambil: reset slot 3
            $timeline[3] = "Siap Di ambil\n" . $order->updated_at->format('d M H.i');
        }

        $order->timeline = $timeline;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Status berhasil di-undo ke ' . $prevStatus,
            'data' => $this->formatOrder($order)
        ]);
    }

    // ─── CANCEL ATAU SOFT DELETE ORDER ─────────────────────────────────────────
    public function destroy($id)
    {
        $order = Order::with(['items', 'service'])->findOrFail($id);
        
        // JIKA PESANAN SUDAH SELESAI / DIBATALKAN -> LAKUKAN SOFT DELETE
        if (in_array($order->status, ['Selesai', 'Dibatalkan'])) {
            $order->delete(); // Ini akan memicu soft delete jika model menggunakan trait SoftDeletes
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dihapus secara permanen dari daftar aktif.'
            ]);
        }

        // JIKA PESANAN BELUM SELESAI/DIBATALKAN -> UBAH STATUS MENJADI DIBATALKAN
        $order->status = 'Dibatalkan';

        $timeline = $order->timeline;
        if (!is_array($timeline) || empty($timeline)) {
            $timeline = [null, null, null, null];
        }
        
        $timeline[3] = "Dibatalkan\n" . Carbon::now()->format('d M H.i');
        
        $order->timeline = $timeline;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => $this->formatOrder($order)
        ]);
    }

    // Data Formatter untuk Response API
    private function formatOrder($order)
    {
        return [
            'id' => $order->id,
            'nota' => $order->nota,
            'nama' => $order->customer_name,
            'berat' => $order->weight . ' Kg',
            'tgl' => $order->order_date ? $order->order_date->format('d F Y') : '-',
            'estimasi' => $order->estimated_date ? $order->estimated_date->format('d F Y') : '-',
            'status' => $order->status,
            'tipe' => ucfirst($order->customer_type),
            'totalHarga' => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'layanan' => $order->service ? $order->service->name : '-',
            'items' => $order->items->map(function ($item) {
                return [
                    'item' => $item->item_name,
                    'jumlah' => floatval($item->quantity) . ' ' . $item->unit,
                    'harga' => 'Rp ' . number_format($item->unit_price, 0, ',', '.'),
                    'sub' => 'Rp ' . number_format($item->subtotal, 0, ',', '.'),
                ];
            }),
            'timeline' => $order->timeline ?? [null, null, null, null],
        ];
    }
}