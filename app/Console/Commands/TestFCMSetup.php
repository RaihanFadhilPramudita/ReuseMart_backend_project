<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FCMService;
use App\Services\NotificationService;
use App\Models\Pembeli;
use App\Models\Penitip;

class TestFCMSetup extends Command
{
    protected $signature = 'fcm:test {action} {user_id?}';
    protected $description = 'Test FCM setup and send notifications';

    public function handle()
    {
        $action = $this->argument('action');
        $userId = $this->argument('user_id');

        switch ($action) {
            case 'setup':
                return $this->testSetup();
            case 'send':
                if (!$userId) {
                    $this->error('User ID required for send action');
                    return 1;
                }
                return $this->testSendNotification($userId);
            case 'tokens':
                return $this->showTokens();
            default:
                $this->error('Invalid action. Use: setup, send {user_id}, or tokens');
                return 1;
        }
    }

    private function testSetup()
    {
        $this->info('🔥 Testing FCM Setup...');
        $this->newLine();

        // Test 1: Configuration
        $this->info('1. Checking configuration...');
        
        $projectId = config('fcm.project_id');
        $serviceAccountPath = config('fcm.service_account_path');
        
        if (empty($projectId)) {
            $this->error('   ❌ FIREBASE_PROJECT_ID not configured in .env');
            return 1;
        }
        $this->info('   ✅ Firebase Project ID: ' . $projectId);

        if (!file_exists($serviceAccountPath)) {
            $this->error('   ❌ Service account file not found at: ' . $serviceAccountPath);
            $this->info('   📝 Please download serviceAccountKey.json from Firebase Console');
            return 1;
        }
        $this->info('   ✅ Service account file exists');

        // Test 2: FCM Service
        $this->info('2. Testing FCM Service...');
        
        try {
            $fcmService = app(FCMService::class);
            
            // Try to validate a dummy token (should work without actual sending)
            $dummyToken = 'c7eK8YY5RGOjVxX8AKuGf7:APA91bF3vQRfZZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZfZ';
            $isValid = $fcmService->validateToken($dummyToken);
            
            if ($isValid) {
                $this->info('   ✅ FCM Service initialized successfully');
            } else {
                $this->error('   ❌ FCM Service validation failed');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('   ❌ FCM Service error: ' . $e->getMessage());
            return 1;
        }

        // Test 3: Database
        $this->info('3. Checking database...');
        
        try {
            \DB::table('notifications')->count();
            $this->info('   ✅ Notifications table exists');
        } catch (\Exception $e) {
            $this->error('   ❌ Notifications table missing. Run: php artisan migrate');
            return 1;
        }

        $this->info('4. Checking FCM tokens in database...');
        
        $pembeliWithToken = Pembeli::whereNotNull('fcm_token')->count();
        $penitipWithToken = Penitip::whereNotNull('fcm_token')->count();
        
        $this->info("   📱 Pembeli with FCM tokens: {$pembeliWithToken}");
        $this->info("   📱 Penitip with FCM tokens: {$penitipWithToken}");

        $this->newLine();
        $this->info('🎉 FCM setup test completed!');
        
        if ($pembeliWithToken == 0 && $penitipWithToken == 0) {
            $this->newLine();
            $this->warn('⚠️  No users with FCM tokens found.');
            $this->info('📋 To test notifications:');
            $this->info('   1. Open Flutter app');
            $this->info('   2. Login with any user');
            $this->info('   3. Run: php artisan fcm:test tokens');
            $this->info('   4. Run: php artisan fcm:test send {user_id}');
        } else {
            $this->newLine();
            $this->info('📋 Ready to test! Run:');
            $this->info('   php artisan fcm:test send {user_id}');
        }
        
        return 0;
    }

    private function testSendNotification($userId)
    {
        $this->info("🔔 Testing notification for user ID: {$userId}");
        
        // Try to find user in both tables
        $pembeli = Pembeli::find($userId);
        $penitip = Penitip::find($userId);
        
        $user = $pembeli ? $pembeli : $penitip;
        $userType = $pembeli ? 'pembeli' : ($penitip ? 'penitip' : null);
        
        if (!$user) {
            $this->error("❌ User ID {$userId} not found in pembeli or penitip tables");
            return 1;
        }

        $userName = $pembeli ? $pembeli->NAMA_PEMBELI : ($penitip ? $penitip->NAMA_PENITIP : 'Unknown');
        $this->info("✅ Found {$userType}: {$userName}");
        
        if (!$user->fcm_token) {
            $this->error("❌ User doesn't have FCM token. User needs to login via mobile app first.");
            return 1;
        }

        $this->info("✅ FCM Token: " . substr($user->fcm_token, 0, 20) . "...");

        // Send test notification
        try {
            $notificationService = app(NotificationService::class);
            
            $title = "🔔 Test Notification";
            $message = "Hello from ReuseMart! This is a test notification sent at " . now()->format('H:i:s');
            $data = [
                'type' => 'test',
                'timestamp' => now()->toISOString(),
                'user_type' => $userType,
                'user_id' => $userId
            ];
            
            $success = $notificationService->sendNotification(
                $userType,
                $userId,
                'test',
                $title,
                $message,
                $data
            );

            if ($success) {
                $this->info("✅ Test notification sent successfully!");
                $this->info("   📱 Check your mobile device for the notification");
                $this->info("   📊 Check database table 'notifications' for record");
            } else {
                $this->error("❌ Failed to send notification");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error sending notification: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function showTokens()
    {
        $this->info('📱 FCM Tokens in Database:');
        $this->newLine();

        $this->info('PEMBELI:');
        $pembeli = Pembeli::whereNotNull('fcm_token')
                          ->select('ID_PEMBELI', 'NAMA_PEMBELI', 'fcm_token', 'fcm_token_updated_at')
                          ->get();

        if ($pembeli->count() > 0) {
            foreach ($pembeli as $p) {
                $this->info("   ID: {$p->ID_PEMBELI} | {$p->NAMA_PEMBELI}");
                $this->info("   Token: " . substr($p->fcm_token, 0, 30) . "...");
                $updatedAt = $p->fcm_token_updated_at ? $p->fcm_token_updated_at : 'N/A';
                $this->info("   Updated: " . $updatedAt);
                $this->info('   ---');
            }
        } else {
            $this->info('   No pembeli with FCM tokens');
        }

        $this->newLine();
        $this->info('PENITIP:');
        $penitip = Penitip::whereNotNull('fcm_token')
                          ->select('ID_PENITIP', 'NAMA_PENITIP', 'fcm_token', 'fcm_token_updated_at')
                          ->get();

        if ($penitip->count() > 0) {
            foreach ($penitip as $p) {
                $this->info("   ID: {$p->ID_PENITIP} | {$p->NAMA_PENITIP}");
                $this->info("   Token: " . substr($p->fcm_token, 0, 30) . "...");
                $updatedAt = $p->fcm_token_updated_at ? $p->fcm_token_updated_at : 'N/A';
                $this->info("   Updated: " . $updatedAt);
                $this->info('   ---');
            }
        } else {
            $this->info('   No penitip with FCM tokens');
        }

        return 0;
    }
}