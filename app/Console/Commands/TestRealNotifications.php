<?php
// 🔥 app/Console/Commands/TestRealNotifications.php - FIXED DATABASE FIELD NAMES

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Barang;
use App\Models\Transaksi;
use App\Models\Penitipan;
use App\Models\DetailPenitipan;
use App\Models\Donasi;
use App\Services\ReuseMartNotificationService;
use Carbon\Carbon;

class TestRealNotifications extends Command
{
    protected $signature = 'notifications:test-real {scenario} {user_id?}';
    protected $description = 'Test real notification scenarios based on business requirements';

    protected $notificationService;

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $scenario = $this->argument('scenario');
        $userId = $this->argument('user_id');

        $this->info("🔔 Testing REAL notification scenario: {$scenario}");
        $this->newLine();

        switch ($scenario) {
            case 'masa-penitipan-h3':
                return $this->testMasaPenitipanH3($userId);
            case 'masa-penitipan-hari-h':
                return $this->testMasaPenitipanHariH($userId);
            case 'barang-terjual':
                return $this->testBarangTerjual($userId);
            case 'jadwal-pengiriman':
                return $this->testJadwalPengiriman($userId);
            case 'status-pengiriman':
                return $this->testStatusPengiriman($userId);
            case 'barang-dikirim':
                return $this->testBarangDikirim($userId);
            case 'barang-sampai':
                return $this->testBarangSampai($userId);
            case 'barang-diambil':
                return $this->testBarangDiambil($userId);
            case 'barang-didonasikan':
                return $this->testBarangDidonasikan($userId);
            case 'donation-warning':
                return $this->testDonationWarning($userId);
            case 'all-scenarios':
                return $this->testAllScenarios();
            default:
                $this->showAvailableScenarios();
                return 1;
        }
    }

    /**
     * 🔥 TEST SCENARIO: Masa penitipan H-3 (REQUIREMENT 123)
     */
    private function testMasaPenitipanH3($penitipId)
    {
        $this->info("Testing MASA PENITIPAN H-3 notification...");
        
        if (!$penitipId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real masa-penitipan-h3 {penitip_id}");
            return 1;
        }

        $penitip = Penitip::find($penitipId);
        if (!$penitip) {
            $this->error("❌ Penitip ID {$penitipId} not found");
            return 1;
        }

        if (!$penitip->fcm_token) {
            $this->error("❌ Penitip doesn't have FCM token. Login via mobile app first.");
            return 1;
        }

        $this->info("✅ Testing with penitip: {$penitip->NAMA_PENITIP}");

        // ✅ FIXED: Get existing pegawai or use first available
        $pegawai = $this->getAvailablePegawai();
        if (!$pegawai) {
            $this->error("❌ No pegawai found in database. Please add pegawai first.");
            return 1;
        }

        // Create penitipan yang akan expire dalam 3 hari
        $penitipan = Penitipan::create([
            'ID_PENITIP' => $penitipId,
            'TANGGAL_MASUK' => now()->subDays(27), // 27 days ago
            'TANGGAL_KADALUARSA' => now()->addDays(3), // expires in 3 days (H-3)
            'TANGGAL_BATAS_AMBIL' => now()->addDays(10),
            'STATUS_PENITIPAN' => true,
            'STATUS_PERPANJANGAN' => false,
        ]);

        // ✅ FIXED: Include ID_PEGAWAI
        $barang = Barang::create([
            'ID_PENITIP' => $penitipId,
            'ID_PEGAWAI' => $pegawai->ID_PEGAWAI, // ✅ FIXED!
            'NAMA_BARANG' => 'Test Laptop H-3',
            'DESKRIPSI' => 'Test item for H-3 notification',
            'HARGA' => 3000000,
            'TANGGAL_MASUK' => now()->subDays(27),
            'STATUS_BARANG' => 'Tersedia',
            'ID_KATEGORI' => 1
        ]);

        DetailPenitipan::create([
            'ID_PENITIPAN' => $penitipan->ID_PENITIPAN,
            'ID_BARANG' => $barang->ID_BARANG,
            'JUMLAH_BARANG_TITIPAN' => 1
        ]);

        // Test notification
        $this->notificationService->sendMasaPenitipanNotification();

        $this->info("✅ H-3 masa penitipan notification test completed");
        $this->info("   📱 Check mobile device for notification");
        $this->info("   📊 Check notifications table in database");

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Masa penitipan HARI H (REQUIREMENT 124)
     */
    private function testMasaPenitipanHariH($penitipId)
    {
        $this->info("Testing MASA PENITIPAN HARI H notification...");
        
        if (!$penitipId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real masa-penitipan-hari-h {penitip_id}");
            return 1;
        }

        $penitip = Penitip::find($penitipId);
        if (!$penitip || !$penitip->fcm_token) {
            $this->error("❌ Invalid penitip or no FCM token");
            return 1;
        }

        $this->info("✅ Testing with penitip: {$penitip->NAMA_PENITIP}");

        // ✅ FIXED: Get existing pegawai
        $pegawai = $this->getAvailablePegawai();
        if (!$pegawai) {
            $this->error("❌ No pegawai found in database.");
            return 1;
        }

        // Create penitipan yang expire hari ini
        $penitipan = Penitipan::create([
            'ID_PENITIP' => $penitipId,
            'TANGGAL_MASUK' => now()->subDays(30),
            'TANGGAL_KADALUARSA' => now(), // expires TODAY (HARI H)
            'TANGGAL_BATAS_AMBIL' => now()->addDays(7),
            'STATUS_PENITIPAN' => true,
            'STATUS_PERPANJANGAN' => false,
        ]);

        // ✅ FIXED: Include ID_PEGAWAI
        $barang = Barang::create([
            'ID_PENITIP' => $penitipId,
            'ID_PEGAWAI' => $pegawai->ID_PEGAWAI, // ✅ FIXED!
            'NAMA_BARANG' => 'Test Smartphone HARI H',
            'DESKRIPSI' => 'Test item for HARI H notification',
            'HARGA' => 2000000,
            'TANGGAL_MASUK' => now()->subDays(30),
            'STATUS_BARANG' => 'Tersedia',
            'ID_KATEGORI' => 1
        ]);

        DetailPenitipan::create([
            'ID_PENITIPAN' => $penitipan->ID_PENITIPAN,
            'ID_BARANG' => $barang->ID_BARANG,
            'JUMLAH_BARANG_TITIPAN' => 1
        ]);

        // Test notification
        $this->notificationService->sendMasaPenitipanNotification();

        $this->info("✅ HARI H masa penitipan notification test completed");
        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Barang terjual (REQUIREMENT 125)
     */
    private function testBarangTerjual($penitipId)
    {
        $this->info("Testing BARANG TERJUAL notification...");
        
        if (!$penitipId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real barang-terjual {penitip_id}");
            return 1;
        }

        $barang = Barang::where('ID_PENITIP', $penitipId)
                        ->where('STATUS_BARANG', 'Tersedia')
                        ->first();

        if (!$barang) {
            $this->error("❌ No available barang found for penitip {$penitipId}");
            $this->info("💡 Create some barang first or use penitip that has barang.");
            return 1;
        }

        // Simulate barang terjual
        $result = $this->notificationService->sendBarangTerjualNotification($barang->ID_BARANG);

        if ($result) {
            $this->info("✅ Barang terjual notification test completed");
            $this->info("   🛍️ Barang: {$barang->NAMA_BARANG}");
        } else {
            $this->error("❌ Failed to send barang terjual notification");
        }

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Jadwal pengiriman (REQUIREMENT 126 & 127)
     */
    private function testJadwalPengiriman($pembeliId)
    {
        $this->info("Testing JADWAL PENGIRIMAN notification...");
        
        if (!$pembeliId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real jadwal-pengiriman {pembeli_id}");
            return 1;
        }

        $pembeli = Pembeli::find($pembeliId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$pembeliId} not found");
            return 1;
        }
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli '{$pembeli->NAMA_PEMBELI}' doesn't have FCM token");
            $this->warn("💡 Login via mobile app first to get FCM token");
            return 1;
        }

        $this->info("✅ Testing with pembeli: {$pembeli->NAMA_PEMBELI} (FCM: " . substr($pembeli->fcm_token, 0, 20) . "...)");

        $transaksi = $this->getOrCreateTestTransaksi($pembeliId);
        
        if (!$transaksi) {
            $this->error("❌ Failed to create or get transaksi");
            return 1;
        }

        // ✅ DEBUG: Show transaksi details
        $this->info("📊 Transaksi details:");
        $this->info("   • ID: {$transaksi->ID_TRANSAKSI}");
        $this->info("   • No Nota: {$transaksi->NO_NOTA}");
        $this->info("   • Detail count: " . $transaksi->detailTransaksi->count());
        
        if ($transaksi->detailTransaksi->count() > 0) {
            foreach ($transaksi->detailTransaksi as $detail) {
                $barang = $detail->barang;
                $penitip = $barang ? $barang->penitip : null;
                $this->info("   • Barang: " . ($barang ? $barang->NAMA_BARANG : 'NULL'));
                if ($penitip) {
                    $this->info("     → Penitip: {$penitip->NAMA_PENITIP} (FCM: " . ($penitip->fcm_token ? 'YES' : 'NO') . ")");
                }
            }
        }

        // Test jadwal pengiriman
        $jadwal = Carbon::now()->addDay()->format('d M Y');
        $this->info("🔔 Sending jadwal notification...");
        
        $result = $this->notificationService->sendJadwalNotification($transaksi->ID_TRANSAKSI, 'Antar', $jadwal);

        if ($result) {
            $this->info("✅ Jadwal pengiriman notification test completed");
            $this->info("   📦 Transaksi: {$transaksi->NO_NOTA}");
            $this->info("   📅 Jadwal: {$jadwal}");
            
            // Show results
            if (is_array($result)) {
                foreach ($result as $recipient => $success) {
                    $status = $success ? '✅ SUCCESS' : '❌ FAILED';
                    $this->info("   • {$recipient}: {$status}");
                }
            }
        } else {
            $this->error("❌ Failed to send jadwal notification");
        }

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Status pengiriman (REQUIREMENT 128, 129, 130)
     */
    private function testStatusPengiriman($pembeliId)
    {
        $this->info("Testing STATUS PENGIRIMAN notifications...");
        
        if (!$pembeliId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real status-pengiriman {pembeli_id}");
            return 1;
        }

        $pembeli = Pembeli::find($pembeliId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$pembeliId} not found");
            return 1;
        }
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli '{$pembeli->NAMA_PEMBELI}' doesn't have FCM token");
            $this->warn("💡 Login via mobile app first to get FCM token");
            return 1;
        }

        $this->info("✅ Testing with pembeli: {$pembeli->NAMA_PEMBELI} (FCM: " . substr($pembeli->fcm_token, 0, 20) . "...)");

        $transaksi = $this->getOrCreateTestTransaksi($pembeliId);
        
        if (!$transaksi) {
            $this->error("❌ Failed to create or get transaksi");
            return 1;
        }

        // ✅ DEBUG: Show transaksi details
        $this->info("📊 Transaksi details:");
        $this->info("   • ID: {$transaksi->ID_TRANSAKSI}");
        $this->info("   • No Nota: {$transaksi->NO_NOTA}");
        $this->info("   • Detail count: " . $transaksi->detailTransaksi->count());

        $statuses = [
            'sedang dikirim',  // REQUIREMENT 128
            'sudah sampai',    // REQUIREMENT 129
            'sudah diambil'    // REQUIREMENT 130
        ];

        foreach ($statuses as $status) {
            $this->info("   🔄 Testing status: {$status}");
            $result = $this->notificationService->sendStatusPengirimanNotification($transaksi->ID_TRANSAKSI, $status);
            
            if ($result) {
                $this->info("   ✅ Status '{$status}' notification sent");
                
                // Show results
                if (is_array($result)) {
                    foreach ($result as $recipient => $success) {
                        $status_icon = $success ? '✅' : '❌';
                        $this->info("     → {$recipient}: {$status_icon}");
                    }
                }
            } else {
                $this->error("   ❌ Failed to send '{$status}' notification");
            }
            
            sleep(1); // Small delay between notifications
        }

        $this->info("✅ All status pengiriman notifications test completed");
        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Barang didonasikan (REQUIREMENT 131)
     */
    private function testBarangDidonasikan($penitipId)
    {
        $this->info("Testing BARANG DIDONASIKAN notification...");
        
        if (!$penitipId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real barang-didonasikan {penitip_id}");
            return 1;
        }

        $penitip = Penitip::find($penitipId);
        if (!$penitip) {
            $this->error("❌ Penitip ID {$penitipId} not found");
            return 1;
        }

        if (!$penitip->fcm_token) {
            $this->error("❌ Penitip doesn't have FCM token. Login via mobile app first.");
            return 1;
        }

        $this->info("✅ Testing with penitip: {$penitip->NAMA_PENITIP}");

        $barang = Barang::where('ID_PENITIP', $penitipId)->first();
        if (!$barang) {
            $this->error("❌ No barang found for penitip {$penitipId}");
            return 1;
        }

        // ✅ CRITICAL DEBUG: Check database schema for donasi table
        $this->info("🔍 Checking donasi table schema...");
        
        try {
            $columns = \DB::select("DESCRIBE donasi");
            $this->info("📋 Donasi table columns:");
            foreach ($columns as $column) {
                $this->info("   • {$column->Field} ({$column->Type}) - " . ($column->Null === 'NO' ? 'REQUIRED' : 'OPTIONAL'));
            }
        } catch (\Exception $e) {
            $this->error("❌ Error checking donasi table: " . $e->getMessage());
        }

        $requestDonasi = $this->getOrCreateRequestDonasi();
        
        if (!$requestDonasi) {
            $this->error("❌ Failed to create request donasi");
            return 1;
        }

        $this->info("✅ Using request donasi ID: {$requestDonasi->ID_REQUEST_DONASI}");

        try {
            // ✅ CRITICAL FIX: Check what the actual field name should be
            // Let's first check if ID_REQUEST field exists, or if it should be ID_REQUEST_DONASI
            
            $this->info("🔧 Creating donasi record...");
            
            // ✅ Try both field naming conventions to see which one works
            $donasiData = [
                'ID_BARANG' => $barang->ID_BARANG,
                'TANGGAL_DONASI' => now()
            ];
            
            // Check if table has ID_REQUEST or ID_REQUEST_DONASI field
            $tableColumns = collect(\DB::select("SHOW COLUMNS FROM donasi"))->pluck('Field')->toArray();
            $this->info("🔍 Available columns: " . implode(', ', $tableColumns));
            
            if (in_array('ID_REQUEST', $tableColumns)) {
                $donasiData['ID_REQUEST'] = $requestDonasi->ID_REQUEST;
                $this->info("✅ Using ID_REQUEST field");
            } elseif (in_array('ID_REQUEST_DONASI', $tableColumns)) {
                $donasiData['ID_REQUEST'] = $requestDonasi->ID_REQUEST;
                $this->info("✅ Using ID_REQUEST field");
            } else {
                $this->error("❌ Neither ID_REQUEST nor ID_REQUEST_DONASI field found in donasi table");
                return 1;
            }

            $donasi = Donasi::create($donasiData);

            $this->info("✅ Donasi record created with ID: {$donasi->ID_DONASI}");

            $result = $this->notificationService->sendBarangDidonasikanNotification($donasi->ID_DONASI);

            if ($result) {
                $this->info("✅ Barang didonasikan notification test completed");
                $this->info("   💚 Barang: {$barang->NAMA_BARANG}");
                $this->info("   📱 Check mobile device for notification");
            } else {
                $this->error("❌ Failed to send barang didonasikan notification");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error creating donasi: " . $e->getMessage());
            $this->info("🔍 SQL State: " . $e->getCode());
            return 1;
        }

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Donation warning
     */
    private function testDonationWarning($penitipId)
    {
        $this->info("Testing DONATION WARNING notification...");
        
        if (!$penitipId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real donation-warning {penitip_id}");
            return 1;
        }

        $penitip = Penitip::find($penitipId);
        if (!$penitip || !$penitip->fcm_token) {
            $this->error("❌ Invalid penitip or no FCM token");
            return 1;
        }

        $this->info("✅ Testing with penitip: {$penitip->NAMA_PENITIP}");

        // ✅ Get existing pegawai
        $pegawai = $this->getAvailablePegawai();
        if (!$pegawai) {
            $this->error("❌ No pegawai found in database.");
            return 1;
        }

        // Create expired penitipan
        $penitipan = Penitipan::create([
            'ID_PENITIP' => $penitipId,
            'TANGGAL_MASUK' => now()->subDays(35),
            'TANGGAL_KADALUARSA' => now()->subDays(5), // Already expired
            'TANGGAL_BATAS_AMBIL' => now()->addDays(2), // Will be donated in 2 days
            'STATUS_PENITIPAN' => true,
            'STATUS_PERPANJANGAN' => false,
        ]);

        // ✅ FIXED: Include ID_PEGAWAI
        $barang = Barang::create([
            'ID_PENITIP' => $penitipId,
            'ID_PEGAWAI' => $pegawai->ID_PEGAWAI, // ✅ FIXED!
            'NAMA_BARANG' => 'Test Item for Donation Warning',
            'DESKRIPSI' => 'This item will be donated soon',
            'HARGA' => 1500000,
            'TANGGAL_MASUK' => now()->subDays(35),
            'STATUS_BARANG' => 'Tersedia',
            'ID_KATEGORI' => 1
        ]);

        DetailPenitipan::create([
            'ID_PENITIPAN' => $penitipan->ID_PENITIPAN,
            'ID_BARANG' => $barang->ID_BARANG,
            'JUMLAH_BARANG_TITIPAN' => 1
        ]);

        $this->notificationService->sendDonationWarningNotification();

        $this->info("✅ Donation warning notification test completed");
        return 0;
    }

    /**
     * 🔥 TEST ALL SCENARIOS
     */
    private function testAllScenarios()
    {
        $this->error("❌ Use specific scenarios instead. Available commands:");
        $this->showAvailableScenarios();
        return 1;
    }

    private function showAvailableScenarios()
    {
        $this->error("❌ Available scenarios (user_id is required):");
        $this->info("   • php artisan notifications:test-real masa-penitipan-h3 {penitip_id}");
        $this->info("   • php artisan notifications:test-real masa-penitipan-hari-h {penitip_id}");
        $this->info("   • php artisan notifications:test-real barang-terjual {penitip_id}");
        $this->info("   • php artisan notifications:test-real jadwal-pengiriman {pembeli_id}");
        $this->info("   • php artisan notifications:test-real status-pengiriman {pembeli_id}");
        $this->info("   • php artisan notifications:test-real barang-didonasikan {penitip_id}");
        $this->info("   • php artisan notifications:test-real donation-warning {penitip_id}");
        $this->newLine();
        $this->info("💡 Make sure users have FCM tokens (login via mobile app first)");
    }

    /**
     * ✅ HELPER: Get available pegawai (use existing, don't create new)
     */
    private function getAvailablePegawai()
    {
        $pegawai = \App\Models\Pegawai::first();
        
        if ($pegawai) {
            $this->info("✅ Using existing pegawai: {$pegawai->NAMA_PEGAWAI} (ID: {$pegawai->ID_PEGAWAI})");
        }
        
        return $pegawai;
    }

    /**
     * ✅ HELPER: Get or create test transaksi
     */
    private function getOrCreateTestTransaksi($pembeliId)
    {
        // Try to find existing transaksi first
        $transaksi = Transaksi::with('detailTransaksi.barang')->where('ID_PEMBELI', $pembeliId)->first();
        
        if (!$transaksi) {
            // ✅ FIXED: Get existing pegawai for transaksi
            $pegawai = $this->getAvailablePegawai();
            if (!$pegawai) {
                $this->error("❌ No pegawai found in database for creating transaksi.");
                return null;
            }
            
            // Create simple test transaksi
            $transaksi = Transaksi::create([
                'ID_PEMBELI' => $pembeliId,
                'ID_PEGAWAI' => $pegawai->ID_PEGAWAI,
                'NO_NOTA' => '25.05.' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'WAKTU_PESAN' => now(),
                'TOTAL_HARGA' => 3000000,
                'ONGKOS_KIRIM' => 100000,
                'TOTAL_AKHIR' => 3100000,
                'STATUS_TRANSAKSI' => 'Diproses',
                'JENIS_DELIVERY' => 'Antar'
            ]);
            
            $this->info("✅ Created test transaksi: {$transaksi->NO_NOTA}");
            
            // ✅ CRITICAL FIX: Create detail_transaksi with actual barang
            $this->createDetailTransaksiForTesting($transaksi);
            
        } else {
            $this->info("✅ Using existing transaksi: {$transaksi->NO_NOTA}");
            
            // Check if existing transaksi has detail_transaksi
            if ($transaksi->detailTransaksi->isEmpty()) {
                $this->info("🔧 Adding detail_transaksi to existing transaksi...");
                $this->createDetailTransaksiForTesting($transaksi);
            }
        }

        // Reload with relations
        $transaksi->load('detailTransaksi.barang.penitip');
        
        return $transaksi;
    }
    
    /**
     * ✅ HELPER: Create detail_transaksi with real barang for testing
     */
    private function createDetailTransaksiForTesting($transaksi)
    {
        // Find some existing barang with penitip that has FCM token
        $barang = Barang::with('penitip')
                        ->whereHas('penitip', function($query) {
                            $query->whereNotNull('fcm_token');
                        })
                        ->where('STATUS_BARANG', 'Tersedia')
                        ->first();
        
        if (!$barang) {
            $this->warn("⚠️ No available barang with penitip FCM token found. Creating simple detail without penitip notification.");
            
            // Get any available barang
            $barang = Barang::where('STATUS_BARANG', 'Tersedia')->first();
            
            if (!$barang) {
                $this->warn("⚠️ No available barang found at all. Transaksi will have no detail_transaksi.");
                return;
            }
        }
        
        // Create detail_transaksi
        \App\Models\DetailTransaksi::create([
            'ID_TRANSAKSI' => $transaksi->ID_TRANSAKSI,
            'ID_BARANG' => $barang->ID_BARANG,
            'JUMLAH' => 1
        ]);
        
        $penitipInfo = $barang->penitip ? 
            "with penitip: {$barang->penitip->NAMA_PENITIP} (FCM: " . ($barang->penitip->fcm_token ? 'YES' : 'NO') . ")" :
            "without penitip";
            
        $this->info("✅ Added detail_transaksi: {$barang->NAMA_BARANG} {$penitipInfo}");
    }

    // ✅ Removed getOrCreateRequestDonasi() method since we use simpler approach
    // that directly gets existing RequestDonasi::first()

    // ✅ Removed getOrCreateTestOrganisasi() method since we use simpler approach
    // that directly uses existing data from database

    private function testBarangDikirim($pembeliId)
    {
        $this->info("Testing BARANG DIKIRIM notification (REQUIREMENT 128)...");
        
        if (!$pembeliId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real barang-dikirim {pembeli_id}");
            return 1;
        }

        $pembeli = Pembeli::find($pembeliId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$pembeliId} not found");
            return 1;
        }
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli '{$pembeli->NAMA_PEMBELI}' doesn't have FCM token");
            $this->warn("💡 Login via mobile app first to get FCM token");
            return 1;
        }

        $this->info("✅ Testing with pembeli: {$pembeli->NAMA_PEMBELI}");

        $transaksi = $this->getOrCreateTestTransaksi($pembeliId);
        
        if (!$transaksi) {
            $this->error("❌ Failed to create or get transaksi");
            return 1;
        }

        $this->info("📦 Transaksi: {$transaksi->NO_NOTA}");
        $this->info("🚚 Sending 'sedang dikirim' notification...");

        $result = $this->notificationService->sendStatusPengirimanNotification($transaksi->ID_TRANSAKSI, 'sedang dikirim');

        if ($result) {
            $this->info("✅ BARANG DIKIRIM notification test completed");
            $this->info("   📱 Check mobile device for notification");
            
            // Show results
            if (is_array($result)) {
                foreach ($result as $recipient => $success) {
                    $status_icon = $success ? '✅' : '❌';
                    $this->info("   • {$recipient}: {$status_icon}");
                }
            }
        } else {
            $this->error("❌ Failed to send barang dikirim notification");
        }

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Barang sampai (REQUIREMENT 129)
     */
    private function testBarangSampai($pembeliId)
    {
        $this->info("Testing BARANG SAMPAI notification (REQUIREMENT 129)...");
        
        if (!$pembeliId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real barang-sampai {pembeli_id}");
            return 1;
        }

        $pembeli = Pembeli::find($pembeliId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$pembeliId} not found");
            return 1;
        }
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli '{$pembeli->NAMA_PEMBELI}' doesn't have FCM token");
            $this->warn("💡 Login via mobile app first to get FCM token");
            return 1;
        }

        $this->info("✅ Testing with pembeli: {$pembeli->NAMA_PEMBELI}");

        $transaksi = $this->getOrCreateTestTransaksi($pembeliId);
        
        if (!$transaksi) {
            $this->error("❌ Failed to create or get transaksi");
            return 1;
        }

        $this->info("📦 Transaksi: {$transaksi->NO_NOTA}");
        $this->info("📍 Sending 'sudah sampai' notification...");

        $result = $this->notificationService->sendStatusPengirimanNotification($transaksi->ID_TRANSAKSI, 'sudah sampai');

        if ($result) {
            $this->info("✅ BARANG SAMPAI notification test completed");
            $this->info("   📱 Check mobile device for notification");
            
            // Show results
            if (is_array($result)) {
                foreach ($result as $recipient => $success) {
                    $status_icon = $success ? '✅' : '❌';
                    $this->info("   • {$recipient}: {$status_icon}");
                }
            }
        } else {
            $this->error("❌ Failed to send barang sampai notification");
        }

        return 0;
    }

    /**
     * 🔥 TEST SCENARIO: Barang diambil (REQUIREMENT 130)
     */
    private function testBarangDiambil($pembeliId)
    {
        $this->info("Testing BARANG DIAMBIL notification (REQUIREMENT 130)...");
        
        if (!$pembeliId) {
            $this->error("❌ User ID required. Usage: php artisan notifications:test-real barang-diambil {pembeli_id}");
            return 1;
        }

        $pembeli = Pembeli::find($pembeliId);
        if (!$pembeli) {
            $this->error("❌ Pembeli ID {$pembeliId} not found");
            return 1;
        }
        
        if (!$pembeli->fcm_token) {
            $this->error("❌ Pembeli '{$pembeli->NAMA_PEMBELI}' doesn't have FCM token");
            $this->warn("💡 Login via mobile app first to get FCM token");
            return 1;
        }

        $this->info("✅ Testing with pembeli: {$pembeli->NAMA_PEMBELI}");

        $transaksi = $this->getOrCreateTestTransaksi($pembeliId);
        
        if (!$transaksi) {
            $this->error("❌ Failed to create or get transaksi");
            return 1;
        }

        $this->info("📦 Transaksi: {$transaksi->NO_NOTA}");
        $this->info("✅ Sending 'sudah diambil' notification...");

        $result = $this->notificationService->sendStatusPengirimanNotification($transaksi->ID_TRANSAKSI, 'sudah diambil');

        if ($result) {
            $this->info("✅ BARANG DIAMBIL notification test completed");
            $this->info("   📱 Check mobile device for notification");
            
            // Show results
            if (is_array($result)) {
                foreach ($result as $recipient => $success) {
                    $status_icon = $success ? '✅' : '❌';
                    $this->info("   • {$recipient}: {$status_icon}");
                }
            }
        } else {
            $this->error("❌ Failed to send barang diambil notification");
        }

        return 0;
    }

    /**
 * ✅ HELPER: Get or create request donasi for testing
 */
private function getOrCreateRequestDonasi()
{
    // Try to find existing request donasi first
    $requestDonasi = \App\Models\RequestDonasi::first();
    
    if ($requestDonasi) {
        $this->info("✅ Using existing request donasi ID: {$requestDonasi->ID_REQUEST_DONASI}");
        return $requestDonasi;
    }
    
    // If no existing request, create one for testing
    // First, get or create test organisasi
    $organisasi = \App\Models\Organisasi::first();
    
    if (!$organisasi) {
        $this->info("🔧 Creating test organisasi...");
        $organisasi = \App\Models\Organisasi::create([
            'NAMA_ORGANISASI' => 'Test Yayasan Sosial',
            'EMAIL' => 'test@yayasan.org',
            'PASSWORD' => bcrypt('password123'),
            'ALAMAT' => 'Jl. Test No. 123, Yogyakarta',
            'NO_TELEPON' => '081234567999',
            'DESKRIPSI' => 'Organisasi test untuk keperluan donation testing'
        ]);
        $this->info("✅ Created test organisasi: {$organisasi->NAMA_ORGANISASI}");
    }
    
        // Create request donasi
        $this->info("🔧 Creating test request donasi...");
        $requestDonasi = \App\Models\RequestDonasi::create([
            'ID_ORGANISASI' => $organisasi->ID_ORGANISASI,
            'JENIS_BARANG' => 'Elektronik dan Peralatan Rumah Tangga',
            'DESKRIPSI_KEBUTUHAN' => 'Membutuhkan laptop, kulkas, dan peralatan elektronik untuk program bantuan sosial',
            'TANGGAL_REQUEST' => now(),
            'STATUS_REQUEST' => 'Aktif'
        ]);
        
        $this->info("✅ Created test request donasi ID: {$requestDonasi->ID_REQUEST_DONASI}");
        return $requestDonasi;
    }

}