<?php
// app/Console/Commands/DebugDonasiCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Donasi;
use App\Models\RequestDonasi;
use App\Models\Barang;

class DebugDonasiCommand extends Command
{
    protected $signature = 'debug:donasi {penitip_id}';
    protected $description = 'Debug donasi creation issue';

    public function handle()
    {
        $penitipId = $this->argument('penitip_id');
        
        $this->info("🐛 DEBUGGING DONASI CREATION ISSUE");
        $this->newLine();

        // 1. Get barang
        $barang = Barang::where('ID_PENITIP', $penitipId)->first();
        if (!$barang) {
            $this->error("❌ No barang found for penitip {$penitipId}");
            return 1;
        }
        $this->info("✅ Found barang: {$barang->NAMA_BARANG} (ID: {$barang->ID_BARANG})");

        // 2. Get request donasi
        $this->info("🔍 Getting request donasi...");
        $requestDonasi = RequestDonasi::first();
        
        if (!$requestDonasi) {
            $this->error("❌ No request donasi found");
            return 1;
        }

        $this->info("✅ Found request donasi:");
        $attributes = $requestDonasi->getAttributes();
        foreach ($attributes as $key => $value) {
            $this->info("   • {$key}: {$value}");
        }

        // 3. Test different ways to get ID_REQUEST
        $this->info("🧪 Testing different ways to get ID_REQUEST:");
        
        // Method 1: Direct property access
        $id1 = $requestDonasi->ID_REQUEST ?? null;
        $this->info("   Method 1 (direct): " . ($id1 ?? 'NULL'));
        
        // Method 2: Array access  
        $id2 = $attributes['ID_REQUEST'] ?? null;
        $this->info("   Method 2 (array): " . ($id2 ?? 'NULL'));
        
        // Method 3: getAttribute
        $id3 = $requestDonasi->getAttribute('ID_REQUEST') ?? null;
        $this->info("   Method 3 (getAttribute): " . ($id3 ?? 'NULL'));

        // Method 4: Magic get
        $id4 = $requestDonasi->{'ID_REQUEST'} ?? null;
        $this->info("   Method 4 (magic): " . ($id4 ?? 'NULL'));

        // Method 5: Raw SQL check
        $rawData = \DB::table('request_donasi')->first();
        $id5 = $rawData->ID_REQUEST ?? null;
        $this->info("   Method 5 (raw SQL): " . ($id5 ?? 'NULL'));

        // 4. Choose the working method
        $finalId = $id1 ?? $id2 ?? $id3 ?? $id4 ?? $id5;
        
        if (!$finalId) {
            $this->error("❌ All methods failed to get ID_REQUEST!");
            return 1;
        }

        $this->info("✅ Final ID_REQUEST to use: {$finalId}");

        // 5. Test donasi creation
        $this->info("🔧 Testing donasi creation...");
        
        $donasiData = [
            'ID_BARANG' => $barang->ID_BARANG,
            'TANGGAL_DONASI' => now()->format('Y-m-d'),
            'ID_REQUEST' => $finalId
        ];

        $this->info("📋 Donasi data:");
        foreach ($donasiData as $key => $value) {
            $type = gettype($value);
            $this->info("   • {$key}: {$value} ({$type})");
        }

        // 6. Final validation
        $this->info("🔍 Final validation:");
        foreach ($donasiData as $key => $value) {
            if ($value === null) {
                $this->error("   ❌ {$key} is NULL");
                return 1;
            } else {
                $this->info("   ✅ {$key} is valid");
            }
        }

        // 7. Create donasi
        try {
            $this->info("💾 Creating donasi record...");
            $donasi = Donasi::create($donasiData);
            
            $this->info("✅ SUCCESS! Donasi created with ID: {$donasi->ID_DONASI}");
            
            // 8. Test notification
            $this->info("📱 Testing notification...");
            $notificationService = app(\App\Services\ReuseMartNotificationService::class);
            $result = $notificationService->sendBarangDidonasikanNotification($donasi->ID_DONASI);
            
            if ($result) {
                $this->info("✅ Notification sent successfully!");
            } else {
                $this->error("❌ Notification failed");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ ERROR: " . $e->getMessage());
            $this->info("🔍 SQL: " . $e->getCode());
            return 1;
        }

        return 0;
    }
}