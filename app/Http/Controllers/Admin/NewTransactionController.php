<?php
// app/Http/Controllers/Admin/NewTransactionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Models\Notification; // <-- Ditambahkan untuk memanggil model Notification
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NewTransactionController extends Controller
{
    // ─── GET /api/admin/transactions/form-data ────────────────────────────────
    public function formData()
    {
        $kiloan = Service::kiloan()->active()->orderBy('id')->get()
            ->map(fn($s) => ['id' => $s->id, 'nama' => $s->name, 'harga' => (int) $s->price]);

        $addon = Service::addon()->active()->orderBy('id')->get()
            ->map(fn($s) => ['id' => $s->id, 'nama' => $s->name, 'harga' => (int) $s->price]);

        $maxBerat = (int) Setting::getValue('max_berat_per_nota', 7);

        return response()->json([
            'success'   => true,
            'kiloan'    => $kiloan,
            'addon'     => $addon,
            'max_berat' => $maxBerat,
        ]);
    }

    // ─── GET /api/admin/transactions/search-member?q=... ─────────────────────
    public function searchMember(Request $request)
    {
        $q = $request->get('q', '');

        if (strlen($q) < 1) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $customers = User::where('role', 'customer')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
            })
            ->select('id', 'name', 'phone', 'address')
            ->withCount('orders')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'phone'       => $u->phone ?? '-',
                'address'     => $u->address ?? '-',
                'totalOrders' => $u->orders_count,
            ]);

        return response()->json(['success' => true, 'data' => $customers]);
    }

    // ─── POST /api/admin/transactions ─────────────────────────────────────────
    // Simpan transaksi baru (Bisa Kiloan Saja, Addon Saja, atau Keduanya)
    public function store(Request $request)
    {
        // 1. Cek isi request secara riil (deteksi input kosong/null dengan method filled)
        $hasKiloan = $request->filled('service_id') && $request->filled('weight') && floatval($request->weight) > 0;
        $hasAddons = $request->has('addons') && is_array($request->addons) && count($request->addons) > 0;

        // Jika dua-duanya kosong, langsung stop di sini
        if (!$hasKiloan && !$hasAddons) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pesanan. Silakan isi layanan Kiloan atau pilih minimal satu Addon.',
            ], 422);
        }

        // 2. Susun Aturan Validasi Dinamis
        $rules = [
            'customer_type'       => 'required|in:member,non-member',
            'user_id'             => 'nullable|exists:users,id',
            'customer_name'       => 'required|string|max:100',
            'customer_phone'      => 'nullable|string|max:20',
            'addons'              => 'nullable|array',
            'addons.*.service_id' => 'required|exists:services,id',
            'addons.*.quantity'   => 'required|integer|min:1',
        ];

        // Jika user mengisi kiloan, tambahkan aturan validasi ketat untuk kiloan
        if ($hasKiloan) {
            $rules['service_id'] = 'required|exists:services,id';
            $rules['weight']     = 'required|numeric|min:0.1';
        }

        // Jalankan validasi Laravel berdasarkan rules di atas
        $data = $request->validate($rules);

        $kiloanTotal   = 0;
        $kiloanService = null;

        // 3. Proses Hitung Layanan Kiloan
        if ($hasKiloan) {
            $kiloanService = Service::findOrFail($data['service_id']);

            // Validasi batas maksimal berat
            $maxBerat = (int) Setting::getValue('max_berat_per_nota', 7);
            if ($data['weight'] > $maxBerat) {
                return response()->json([
                    'success' => false,
                    'message' => "Berat melebihi batas maksimal {$maxBerat} Kg per nota.",
                ], 422);
            }

            $kiloanTotal = $data['weight'] * $kiloanService->price;
        }

        // 4. Proses Hitung Layanan Addon
        $addonTotal = 0;
        $addonItems = [];

        if ($hasAddons) {
            foreach ($data['addons'] as $addon) {
                $addonService = Service::find($addon['service_id']);
                if (!$addonService) continue;

                $subtotal    = $addon['quantity'] * $addonService->price;
                $addonTotal += $subtotal;
                $addonItems[] = [
                    'service'  => $addonService,
                    'quantity' => $addon['quantity'],
                    'subtotal' => $subtotal,
                ];
            }
        }

        // Total gabungan keseluruhan
        $totalPrice = $kiloanTotal + $addonTotal;

        // Buat nomor nota unik
        $nota = $this->generateNota();

        // Timeline awal transaksi
        $timeline = [
            "Order di terima\n" . Carbon::now()->format('d M H.i'),
            null,
            null,
            null,
            null,
        ];

        // Ambil ID layanan pertama jika transaksi tidak menggunakan layanan kiloan
        $serviceId = null;
        if ($hasKiloan) {
            $serviceId = $kiloanService->id;
        } else {
            $firstService = Service::kiloan()->active()->orderBy('id')->first();
            $serviceId = $firstService ? $firstService->id : null;
        }

        // 5. Simpan data utama ke tabel Orders
        $order = Order::create([
            'nota'           => $nota,
            'user_id'        => $data['user_id'] ?? null,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_type'  => $data['customer_type'],
            'service_id'     => $serviceId, // Terisi ID inputan atau ID layanan pertama jika kosong
            'weight'         => $hasKiloan ? $data['weight'] : 0,        // Set 0 jika tanpa kiloan
            'total_price'    => $totalPrice,
            'status'         => 'Order Diterima',
            'timeline'       => $timeline,
            'order_date'     => Carbon::today(),
            'estimated_date' => Carbon::tomorrow(),
        ]);

        // 6. Simpan rincian ke tabel Order Items
        // Simpan baris Kiloan hanya jika diisi
        if ($hasKiloan) {
            OrderItem::create([
                'order_id'   => $order->id,
                'service_id' => $kiloanService->id,
                'item_name'  => 'Kiloan',
                'quantity'   => $data['weight'],
                'unit'       => 'kg',
                'unit_price' => $kiloanService->price,
                'subtotal'   => $kiloanTotal,
            ]);
        }

        // Simpan baris Addon jika ada
        if ($hasAddons) {
            foreach ($addonItems as $ai) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'service_id' => $ai['service']->id,
                    'item_name'  => $ai['service']->name,
                    'quantity'   => $ai['quantity'],
                    'unit'       => 'pcs',
                    'unit_price' => $ai['service']->price,
                    'subtotal'   => $ai['subtotal'],
                ]);
            }
        }

        // Muat ulang relasi data untuk dikembalikan ke frontend
        $order->load(['service', 'items.service']);

        // 7. KIRIM NOTIFIKASI PESANAN BARU KE PELANGGAN
        $this->sendCustomerNotification($order, 'Order Diterima');

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat',
            'data'    => $this->formatOrder($order),
        ], 201);
    }

    // ─── Generate nomor nota unik ─────────────────────────────────────────────
    private function generateNota(): string
    {
        do {
            $nota = str_pad(random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        } while (Order::where('nota', $nota)->exists());

        return $nota;
    }

    // ─── Format response order ────────────────────────────────────────────────
    private function formatOrder(Order $order): array
    {
        return [
            'id'          => $order->id,
            'nota'        => $order->nota,
            'nama'        => $order->customer_name,
            'tipe'        => $order->customer_type === 'member' ? 'Member' : 'Non-Member',
            'berat'       => $order->weight > 0 ? $order->weight . ' Kg' : '-', // Tampilkan strip jika tanpa kiloan
            'tgl'         => Carbon::parse($order->order_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'estimasi'    => Carbon::parse($order->estimated_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'status'      => $order->status,
            // Jika berat > 0 berarti dia transaksi kiloan riil, tampilkan nama layanannya. Jika berat 0, kembalikan 'Hanya Satuan'.
            'layanan'     => $order->weight > 0 ? ($order->service->name ?? 'Hanya Satuan') : 'Hanya Satuan',
            'totalHarga'  => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'timeline'    => $order->timeline ?? [null, null, null, null, null],
            'items'       => $order->items->map(fn($item) => [
                'item'   => $item->item_name,
                'jumlah' => $item->unit === 'kg'
                    ? floatval($item->quantity) . ' kg'
                    : intval($item->quantity) . ' pcs',
                'harga'  => 'Rp ' . number_format($item->unit_price, 0, ',', '.'),
                'sub'    => 'Rp ' . number_format($item->subtotal, 0, ',', '.'),
            ])->toArray(),
        ];
    }

    // ─── PRIVATE: Kirim notifikasi ke customer ──────────────────────────────
    private function sendCustomerNotification(Order $order, string $status): void
    {
        // Hanya proses jika pelanggan memiliki user_id (member)
        if (!$order->user_id) {
            return; 
        }

        $map = [
            'Order Diterima'  => [
                'title'   => 'Pesanan Anda Kami Terima',
                'message' => "Pesanan Nota #{$order->nota} Anda telah kami terima dan sedang dalam antrean proses.",
            ],
            // Tambahkan status lain di sini di masa depan jika diperlukan
        ];

        if (!isset($map[$status])) {
            return;
        }

        Notification::create([
            'user_id'  => $order->user_id,
            'order_id' => $order->id,
            'title'    => $map[$status]['title'],
            'message'  => $map[$status]['message'],
            'is_read'  => false,
        ]);
    }
}