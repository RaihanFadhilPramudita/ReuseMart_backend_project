<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Barang;
use App\Services\ReuseMartNotificationService;
use App\Services\NotificationService;

class TestNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {type} {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending notifications to users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');
        $userId = $this->argument('user_id');

        $this->info("🔔 Testing notification type: {$type} for user ID: {$userId}");
        $this->newLine();

        try {
            switch ($type) {
                case 'barang-terjual':
                    return $this->testBarangTerjualNotification($userId);

                case 'masa-penitipan':
                    return $this->testMasaPenitipanNotification($userId);

                case 'pengiriman':
                    return $this->testPengirimanNotification($userId);

                case 'donation-warning':
                    return $this->testDonationWarningNotification($userId);

                default:
                    $this->error("❌ Type tidak valid. Gunakan salah satu dari:");
                    $this->info("   • barang-terjual");
                    $this->info("   • masa-penitipan");
                    $this->info("   • pengiriman");
                    $this->info("   • donation-warning");
                    return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("   Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Test barang terjual notification for penitip
     * FIXED: Parameter sekarang adalah penitip_id, bukan barang_id
     */
    private function testBarangTerjualNotification($penitipId)
    {
        $this->info("Testing 'Barang Terjual' notification...");
        
        $penitip = Penitip::find($penitipId);
        if (!$penitip) {
            $this->error("❌ Penitip ID {$penitipId} tidak ditemukan");
            return 1;
        }

        $this->info("✅ Penitip found: {$penitip->NAMA_PENITIP}");
        
        if (!$penitip->fcm_token) {
            $this->error("❌ Penitip tidak punya FCM token.");
            $this->warn("   💡 Penitip harus login dulu di mobile app untuk mendapatkan FCM token.");
            return 1;
        }

        $this->info("✅ FCM Token exists: " . substr($penitip->fcm_token, 0, 20) . "...");

        // ✅ FIXED: Cari barang milik penitip ini untuk test
        $barang = Barang::where('ID_PENITIP', $penitipId)
                        ->where('STATUS_BARANG', 'Tersedia')
                        ->first();

        if (!$barang) {
            $this->error("❌ Tidak ada barang tersedia untuk penitip ID {$penitipId}");
            $this->warn("   💡 Tambahkan barang dulu atau gunakan penitip yang punya barang");
            return 1;
        }

        $this->info("✅ Using barang: {$barang->NAMA_BARANG} (ID: {$barang->ID_BARANG})");

        // Test Firebase availability first
        $notificationService = app(NotificationService::class);
        if (!$notificationService->isFirebaseAvailable()) {
            $this->error("❌ Firebase tidak tersedia. Cek konfigurasi Firebase.");
            $this->warn("   💡 Jalankan: php artisan firebase:test");
            return 1;
        }

        $this->info("✅ Firebase messaging available");

        // ✅ FIXED: Kirim barang_id ke service, bukan penitip_id
        $reuseMartService = app(ReuseMartNotificationService::class);
        $result = $reuseMartService->sendBarangTerjualNotification($barang->ID_BARANG);

        if ($result) {
            $this->info("✅ Test notification 'barang terjual' berhasil dikirim ke penitip {$penitipId}");
            $this->info("   📱 Cek device untuk melihat notification");
            $this->info("   🛍️ Barang: {$barang->NAMA_BARANG}");
        } else {
            $this->error("❌ Gagal mengirim notification");
            $this->warn("   💡 Cek Laravel logs untuk detail error: tail -f storage/logs/laravel.log");
        }

        return 0;
    }

    /**
     * Test masa penitipan notification for penitip
     */
    private function testMasaPenitipanNotification($userId)
    {
        $this->info("Testing 'Masa Penitipan' notification...");
        
        $penitip = Penitip::find($userId);
        if (!$penitip) {
            $this->error("❌ Penitip ID {$userId} tidak ditemukan");
            return 1;
        }

        $this->info("✅ Penitip found: {$penitip->NAMA_PENITIP}");
        
        if (!$penitip->fcm_token) {
            $this->error("❌ Penitip tidak punya FCM token.");
            $this->warn("   💡 Penitip harus login dulu di mobile app untuk mendapatkan FCM token.");
            return 1;
        }

        $this->info("✅ FCM Token exists: " . substr($penitip->fcm_token, 0, 20) . "...");

        // Send notification
        $notificationService = app(ReuseMartNotificationService::class);
        $result = $notificationService->sendMasaPenitipanNotification();

        if ($result) {
            $this->info("✅ Test notification 'masa penitipan' berhasil dikirim");
            $this->info("   📱 Cek device untuk melihat notification");
        } else {
            $this->error("❌ Gagal mengirim notification");
        }

        return 0;
    }

    /**
     * Test pengiriman notification for pembeli
     */
    private function testPengirimanNotification($userId)
    {
        $this->info("Testing 'Pengiriman' notification...");
        
        $pembeli = Pembeli::find($userId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$userId} tidak ditemukan");
            return 1;
        }

        $this->info("✅ Pembeli found: {$pembeli->NAMA_PEMBELI}");
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli tidak punya FCM token.");
            $this->warn("   💡 Pembeli harus login dulu di mobile app untuk mendapatkan FCM token.");
            return 1;
        }

        $this->info("✅ FCM Token exists: " . substr($pembeli->fcm_token, 0, 20) . "...");

        // ✅ FIXED: Cari transaksi milik pembeli ini
        $transaksi = \App\Models\Transaksi::where('ID_PEMBELI', $userId)->first();
        
        if (!$transaksi) {
            $this->error("❌ Tidak ada transaksi untuk pembeli ID {$userId}");
            return 1;
        }

        $this->info("✅ Using transaksi: {$transaksi->NO_NOTA}");

        // Send notification
        $notificationService = app(ReuseMartNotificationService::class);
        $result = $notificationService->sendStatusPengirimanNotification($transaksi->ID_TRANSAKSI, 'sedang dikirim');

        if ($result) {
            $this->info("✅ Test notification 'pengiriman' berhasil dikirim ke pembeli {$userId}");
            $this->info("   📱 Cek device untuk melihat notification");
        } else {
            $this->error("❌ Gagal mengirim notification");
        }

        return 0;
    }

    /**
     * Test donation warning notification for penitip
     */
    private function testDonationWarningNotification($userId)
    {
        $this->info("Testing 'Donation Warning' notification...");
        
        $penitip = Penitip::find($userId);
        if (!$penitip) {
            $this->error("❌ Penitip ID {$userId} tidak ditemukan");
            return 1;
        }

        $this->info("✅ Penitip found: {$penitip->NAMA_PENITIP}");
        
        if (!$penitip->fcm_token) {
            $this->error("❌ Penitip tidak punya FCM token.");
            $this->warn("   💡 Penitip harus login dulu di mobile app untuk mendapatkan FCM token.");
            return 1;
        }

        $this->info("✅ FCM Token exists: " . substr($penitip->fcm_token, 0, 20) . "...");

        // Send notification
        $notificationService = app(ReuseMartNotificationService::class);
        $result = $notificationService->sendDonationWarningNotification();

        if ($result) {
            $this->info("✅ Test notification 'donation warning' berhasil dikirim");
            $this->info("   📱 Cek device untuk melihat notification");
        } else {
            $this->error("❌ Gagal mengirim notification");
        }

        return 0;
    }
}