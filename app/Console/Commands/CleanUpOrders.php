<?php
// app/Console/Commands/CleanUpOrders.php
namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanUpOrders extends Command
{
    /**
     * Nama & signature command (dipanggil via: php artisan orders:cleanup)
     */
    protected $signature = 'orders:cleanup
                            {--dry-run : Simulasi tanpa benar-benar menghapus data}';

    protected $description = 'Soft-delete order selesai/batal (>7 hari) & force-delete order yang sudah soft-deleted (>30 hari)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('======================================');
        $this->info('  CleanUpOrders — ' . now()->toDateTimeString());
        if ($isDryRun) {
            $this->warn('  MODE: DRY RUN (tidak ada data yang dihapus)');
        }
        $this->info('======================================');

        $this->processTask1SoftDelete($isDryRun);
        $this->newLine();
        $this->processTask2ForceDelete($isDryRun);

        $this->newLine();
        $this->info('✅ CleanUpOrders selesai.');

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────
    // TASK 1 — Soft Delete
    //
    // Target : Order dengan status 'Selesai' atau 'Dibatalkan'
    //          yang kolom `updated_at`-nya sudah <= 7 hari yang lalu.
    //
    // Kenapa `updated_at`?
    //   Kolom ini otomatis diperbarui Laravel setiap kali status berubah.
    //   Jadi `updated_at <= now()->subDays(7)` artinya:
    //   "record ini tidak diubah (termasuk status) selama minimal 7 hari"
    //   → berarti sudah 7 hari berada di status terminal.
    // ──────────────────────────────────────────────────────────────
    private function processTask1SoftDelete(bool $isDryRun): void
    {
        $this->info('📦 TASK 1 — Soft Delete (status terminal > 7 hari)');
        $this->line('   Kriteria  : status IN (Selesai, Dibatalkan)');
        $this->line('   Batas     : updated_at <= ' . now()->subDays(7)->toDateTimeString());

        $softDeletedCount = 0;

        // chunkById mencegah out-of-memory pada dataset besar.
        // Memproses 200 record per iterasi, lebih aman daripada get() sekaligus.
        Order::readyToSoftDelete()
            ->chunkById(200, function ($orders) use ($isDryRun, &$softDeletedCount) {
                foreach ($orders as $order) {
                    $this->line("   → Soft-delete: #{$order->nota} | {$order->status} | last update: {$order->updated_at}");

                    if (! $isDryRun) {
                        $order->delete(); // SoftDeletes: mengisi kolom `deleted_at`
                    }

                    $softDeletedCount++;
                }
            });

        $label = $isDryRun ? '[DRY RUN] Akan di-soft-delete' : 'Berhasil di-soft-delete';
        $this->info("   ✔ {$label}: {$softDeletedCount} order");
        Log::info("[CleanUpOrders] Task1 SoftDelete: {$softDeletedCount} records" . ($isDryRun ? ' (dry-run)' : ''));
    }

    // ──────────────────────────────────────────────────────────────
    // TASK 2 — Force Delete
    //
    // Target : Order yang sudah di-soft-delete
    //          dan kolom `deleted_at`-nya sudah <= 30 hari yang lalu.
    //
    // Kenapa `deleted_at`?
    //   Saat soft-delete terjadi, Laravel mengisi `deleted_at` = waktu saat itu.
    //   Jadi `deleted_at <= now()->subDays(30)` artinya:
    //   "record ini sudah 'berada di tong sampah' selama minimal 30 hari"
    //   → sudah waktunya dihapus permanen.
    //
    // PENTING: Wajib pakai onlyTrashed() agar query builder
    //   tidak mengabaikan record yang deleted_at-nya tidak null.
    // ──────────────────────────────────────────────────────────────
    private function processTask2ForceDelete(bool $isDryRun): void
    {
        $this->info('🗑️  TASK 2 — Force Delete (soft-deleted > 30 hari)');
        $this->line('   Kriteria  : deleted_at IS NOT NULL');
        $this->line('   Batas     : deleted_at <= ' . now()->subDays(30)->toDateTimeString());

        $forceDeletedCount = 0;

        Order::readyToForceDelete()
            ->chunkById(200, function ($orders) use ($isDryRun, &$forceDeletedCount) {
                foreach ($orders as $order) {
                    $this->line("   → Force-delete: #{$order->nota} | soft-deleted at: {$order->deleted_at}");

                    if (! $isDryRun) {
                        $order->forceDelete(); // Hapus permanen dari database
                    }

                    $forceDeletedCount++;
                }
            });

        $label = $isDryRun ? '[DRY RUN] Akan di-force-delete' : 'Berhasil di-force-delete';
        $this->info("   ✔ {$label}: {$forceDeletedCount} order");
        Log::info("[CleanUpOrders] Task2 ForceDelete: {$forceDeletedCount} records" . ($isDryRun ? ' (dry-run)' : ''));
    }
}