<?php
// app/Http/Controllers/Admin/NewTransactionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NewTransactionController extends Controller
{
    // ─── GET /api/admin/transactions/form-data ────────────────────────────────
    // Data awal yang dibutuhkan form:
    //   - services kiloan  (dari DB, harga real-time)
    //   - services addon   (dari DB, harga real-time)
    //   - max_berat        (dari settings)
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
    // Cari pelanggan member berdasarkan nama atau no HP
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
            ->withCount('orders') // total order pelanggan
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
    // Simpan transaksi baru
    // Body (JSON):
    // {
    //   "customer_type": "member" | "non-member",
    //   "user_id": 3,                        // hanya jika member
    //   "customer_name": "Budi Santoso",     // wajib
    //   "customer_phone": "081234567890",    // opsional (non-member)
    //   "service_id": 1,                     // kiloan service id
    //   "weight": 4.5,
    //   "addons": [                          // array addon (qty > 0 saja)
    //     { "service_id": 4, "quantity": 1 },
    //     { "service_id": 5, "quantity": 2 }
    //   ]
    // }
    public function store(Request $request)
    {
        // ── Validasi ──────────────────────────────────────────────────────────
        $data = $request->validate([
            'customer_type'  => 'required|in:member,non-member',
            'user_id'        => 'nullable|exists:users,id',
            'customer_name'  => 'required|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'service_id'     => 'required|exists:services,id',
            'weight'         => 'required|numeric|min:0.1',
            'addons'         => 'nullable|array',
            'addons.*.service_id' => 'required|exists:services,id',
            'addons.*.quantity'   => 'required|integer|min:1',
        ]);

        // ── Ambil layanan kiloan ───────────────────────────────────────────────
        $kiloanService = Service::findOrFail($data['service_id']);

        // ── Cek max berat ─────────────────────────────────────────────────────
        $maxBerat = (int) Setting::getValue('max_berat_per_nota', 7);
        if ($data['weight'] > $maxBerat) {
            return response()->json([
                'success' => false,
                'message' => "Berat melebihi batas maksimal {$maxBerat} Kg per nota.",
            ], 422);
        }

        // ── Hitung total harga ────────────────────────────────────────────────
        $kiloanTotal = $data['weight'] * $kiloanService->price;
        $addonTotal  = 0;
        $addonItems  = [];

        if (!empty($data['addons'])) {
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

        $totalPrice = $kiloanTotal + $addonTotal;

        // ── Buat nomor nota unik (8 digit, format: DDMMHHMM) ──────────────────
        $nota = $this->generateNota();

        // ── Timeline awal ─────────────────────────────────────────────────────
        $timeline = [
            "Order di terima\n" . Carbon::now()->format('d M H.i'),
            null,
            null,
            null,
        ];

        // ── Buat order ────────────────────────────────────────────────────────
        $order = Order::create([
            'nota'           => $nota,
            'user_id'        => $data['user_id'] ?? null,
            'customer_name'  => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_type'  => $data['customer_type'],
            'service_id'     => $kiloanService->id,
            'weight'         => $data['weight'],
            'total_price'    => $totalPrice,
            'status'         => 'Sedang Dicuci',
            'timeline'       => $timeline,
            'order_date'     => Carbon::today(),
            'estimated_date' => Carbon::tomorrow(),
        ]);

        // ── Buat order items ───────────────────────────────────────────────────

        // Item kiloan
        OrderItem::create([
            'order_id'   => $order->id,
            'service_id' => $kiloanService->id,
            'item_name'  => 'Kiloan',
            'quantity'   => $data['weight'],
            'unit'       => 'kg',
            'unit_price' => $kiloanService->price,
            'subtotal'   => $kiloanTotal,
        ]);

        // Item addon
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

        // ── Response ──────────────────────────────────────────────────────────
        $order->load(['service', 'items.service']);

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
            // Format: 8 digit angka acak
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
            'berat'       => $order->weight . ' Kg',
            'tgl'         => Carbon::parse($order->order_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'estimasi'    => Carbon::parse($order->estimated_date)->locale('id')->isoFormat('D MMMM YYYY'),
            'status'      => $order->status,
            'layanan'     => $order->service->name ?? '-',
            'totalHarga'  => 'Rp ' . number_format($order->total_price, 0, ',', '.'),
            'timeline'    => $order->timeline ?? [null, null, null, null],
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
}
