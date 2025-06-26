<?php
// app/Console/Commands/CreateTestUser.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Barang;
use App\Models\Transaksi;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    protected $signature = 'test:create-user {type} {--with-fcm}';
    protected $description = 'Create test user for notification testing';

    public function handle()
    {
        $type = $this->argument('type');
        $withFcm = $this->option('with-fcm');

        // Generate fake FCM token for testing
        $fakeFcmToken = $withFcm ? 'test_fcm_token_' . uniqid() : null;

        switch ($type) {
            case 'pembeli':
                $pembeli = Pembeli::create([
                    'EMAIL' => 'test.pembeli@reusemart.com',
                    'PASSWORD' => Hash::make('password123'),
                    'NAMA_PEMBELI' => 'Test Pembeli',
                    'NO_TELEPON' => '081234567890',
                    'TANGGAL_LAHIR' => '1990-01-01',
                    'TANGGAL_REGISTRASI' => now(),
                    'POIN' => 100,
                    'fcm_token' => $fakeFcmToken,
                    'fcm_token_updated_at' => now()
                ]);

                $this->info("✅ Test Pembeli created:");
                $this->info("   ID: {$pembeli->ID_PEMBELI}");
                $this->info("   Email: {$pembeli->EMAIL}");
                $this->info("   FCM Token: " . ($fakeFcmToken ? 'YES' : 'NO'));
                break;

            case 'penitip':
                $penitip = Penitip::create([
                    'EMAIL' => 'test.penitip@reusemart.com',
                    'PASSWORD' => Hash::make('password123'),
                    'NAMA_PENITIP' => 'Test Penitip',
                    'NO_TELEPON' => '081234567891',
                    'NO_KTP' => '1234567890123456',
                    'TANGGAL_LAHIR' => '1985-05-15',
                    'TANGGAL_REGISTRASI' => now(),
                    'SALDO' => 0,
                    'RATING' => 5.0,
                    'POIN_SOSIAL' => 50,
                    'fcm_token' => $fakeFcmToken,
                    'fcm_token_updated_at' => now()
                ]);

                $this->info("✅ Test Penitip created:");
                $this->info("   ID: {$penitip->ID_PENITIP}");
                $this->info("   Email: {$penitip->EMAIL}");
                $this->info("   FCM Token: " . ($fakeFcmToken ? 'YES' : 'NO'));
                break;

            case 'barang':
                // Cari test penitip
                $penitip = Penitip::where('EMAIL', 'test.penitip@reusemart.com')->first();
                if (!$penitip) {
                    $this->error("❌ Test penitip not found. Create penitip first: php artisan test:create-user penitip --with-fcm");
                    return 1;
                }

                $barang = Barang::create([
                    'ID_PENITIP' => $penitip->ID_PENITIP,
                    'NAMA_BARANG' => 'Test Laptop Gaming',
                    'DESKRIPSI' => 'Laptop gaming bekas untuk testing notification',
                    'HARGA' => 5000000,
                    'TANGGAL_MASUK' => now(),
                    'STATUS_BARANG' => 'Tersedia',
                    'STATUS_GARANSI' => true,
                    'TANGGAL_GARANSI' => now()->addMonths(6),
                    'ID_KATEGORI' => 1
                ]);

                $this->info("✅ Test Barang created:");
                $this->info("   ID: {$barang->ID_BARANG}");
                $this->info("   Nama: {$barang->NAMA_BARANG}");
                $this->info("   Penitip: {$penitip->NAMA_PENITIP}");
                break;

            case 'transaksi':
                // Cari test pembeli dan barang
                $pembeli = Pembeli::where('EMAIL', 'test.pembeli@reusemart.com')->first();
                $barang = Barang::where('NAMA_BARANG', 'Test Laptop Gaming')->first();

                if (!$pembeli || !$barang) {
                    $this->error("❌ Test pembeli or barang not found. Create them first.");
                    return 1;
                }

                $transaksi = Transaksi::create([
                    'ID_PEMBELI' => $pembeli->ID_PEMBELI,
                    'NO_NOTA' => '25.05.' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                    'WAKTU_PESAN' => now(),
                    'TOTAL_HARGA' => $barang->HARGA,
                    'ONGKOS_KIRIM' => 100000,
                    'TOTAL_AKHIR' => $barang->HARGA + 100000,
                    'STATUS_TRANSAKSI' => 'Diproses',
                    'JENIS_DELIVERY' => 'Kurir'
                ]);

                $this->info("✅ Test Transaksi created:");
                $this->info("   ID: {$transaksi->ID_TRANSAKSI}");
                $this->info("   No Nota: {$transaksi->NO_NOTA}");
                $this->info("   Status: {$transaksi->STATUS_TRANSAKSI}");
                break;

            default:
                $this->error("❌ Invalid type. Use: pembeli, penitip, barang, or transaksi");
                return 1;
        }

        return 0;
    }
}