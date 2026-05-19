<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    /**
     * GET /customer/notifications
     * Ambil semua notifikasi milik user yang sedang login.
     */
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($n) => $this->format($n));

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    /**
     * PATCH /customer/notifications/{id}/read
     * Tandai satu notifikasi sebagai sudah dibaca.
     */
    public function markRead(Request $request, $id)
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notif->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi ditandai sudah dibaca.',
        ]);
    }

    /**
     * PATCH /customer/notifications/read-all
     * Tandai semua notifikasi milik user sebagai sudah dibaca.
     */
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi sudah ditandai dibaca.',
        ]);
    }

    /**
     * GET /customer/notifications/unread-count
     * Hitung jumlah notifikasi yang belum dibaca (untuk badge di sidebar).
     */
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'count'   => $count,
        ]);
    }

    /**
     * DELETE /customer/notifications/read
     * Hapus semua notifikasi yang sudah dibaca milik user.
     * Notifikasi yang belum dibaca (is_read = false) tetap tersimpan.
     */
    public function deleteRead(Request $request)
    {
        $deleted = Notification::where('user_id', $request->user()->id)
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} notifikasi yang sudah dibaca berhasil dihapus.",
        ]);
    }

    // ─── Private Helper ───────────────────────────────────────────────────────

    private function format(Notification $n): array
    {
        return [
            'id'         => $n->id,
            'order_id'   => $n->order_id,
            'title'      => $n->title,
            'message'    => $n->message,
            'is_read'    => $n->is_read,
            'waktu'      => $n->created_at->format('H:i'),
            'tanggal'    => $n->created_at->format('d/m/Y'),
            'created_at' => $n->created_at->toISOString(),
        ];
    }
}