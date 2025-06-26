<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestFirebaseSetup extends Command
{
    protected $signature = 'firebase:test';
    protected $description = 'Test Firebase Admin SDK configuration and connectivity';

    public function handle()
    {
        $this->info('🔥 Testing Firebase Admin SDK Setup...');
        $this->newLine();

        // Test 1: Configuration
        $this->info('1. Checking Firebase configuration...');
        
        $projectId = config('firebase.project_id');
        $credentialsPath = config('firebase.credentials.file');
        
        if (empty($projectId)) {
            $this->error('   ❌ FIREBASE_PROJECT_ID not configured in .env');
            $this->warn('   💡 Add this to your .env file:');
            $this->warn('      FIREBASE_PROJECT_ID=your-firebase-project-id');
            return 1;
        }
        $this->info('   ✅ Firebase Project ID configured: ' . $projectId);

        if (empty($credentialsPath)) {
            $this->error('   ❌ FIREBASE_CREDENTIALS path not configured');
            return 1;
        }
        $this->info('   ✅ Credentials path configured: ' . $credentialsPath);

        // Test 2: Service Account File
        $this->info('2. Checking service account file...');
        
        if (!file_exists($credentialsPath)) {
            $this->error('   ❌ Service account file not found at: ' . $credentialsPath);
            $this->newLine();
            $this->warn('   📝 To fix this:');
            $this->warn('   1. Go to Firebase Console > Project Settings > Service Accounts');
            $this->warn('   2. Click "Generate new private key"');
            $this->warn('   3. Download the JSON file');
            $this->warn('   4. Rename it to serviceAccountKey.json');
            $this->warn('   5. Place it at: ' . $credentialsPath);
            $this->newLine();
            return 1;
        }
        $this->info('   ✅ Service account file exists');

        // Validate JSON format
        $jsonContent = file_get_contents($credentialsPath);
        $decoded = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('   ❌ Service account file is not valid JSON');
            return 1;
        }
        $this->info('   ✅ Service account file is valid JSON');

        // Test 3: Firebase Admin SDK connection
        $this->info('3. Testing Firebase Admin SDK connection...');
        try {
            $factory = (new \Kreait\Firebase\Factory)
                ->withServiceAccount($credentialsPath)
                ->withProjectId($projectId);

            $messaging = $factory->createMessaging();
            $this->info('   ✅ Firebase Admin SDK initialized successfully');
            $this->info('   ✅ Firebase Cloud Messaging service available');
        } catch (\Kreait\Firebase\Exception\InvalidArgumentException $e) {
            $this->error('   ❌ Invalid Firebase configuration: ' . $e->getMessage());
            return 1;
        } catch (\Exception $e) {
            $this->error('   ❌ Firebase Admin SDK failed: ' . $e->getMessage());
            $this->warn('   💡 Check your internet connection and Firebase project settings');
            return 1;
        }

        // Test 4: Database tables
        $this->info('4. Checking database tables...');
        
        try {
            \DB::table('notifications')->count();
            $this->info('   ✅ Notifications table exists');
        } catch (\Exception $e) {
            $this->error('   ❌ Notifications table missing');
            $this->warn('   💡 Run: php artisan migrate');
            return 1;
        }

        // Test 5: FCM tokens in database
        $this->info('5. Checking FCM tokens in database...');
        
        $pembeliWithToken = \App\Models\Pembeli::whereNotNull('fcm_token')->count();
        $penitipWithToken = \App\Models\Penitip::whereNotNull('fcm_token')->count();
        $pegawaiWithToken = \App\Models\Pegawai::whereNotNull('fcm_token')->count();
        
        $this->info("   📱 Pembeli with FCM tokens: {$pembeliWithToken}");
        $this->info("   📱 Penitip with FCM tokens: {$penitipWithToken}");
        $this->info("   📱 Pegawai with FCM tokens: {$pegawaiWithToken}");
        
        $totalTokens = $pembeliWithToken + $penitipWithToken + $pegawaiWithToken;
        if ($totalTokens == 0) {
            $this->warn('   ⚠️  No users with FCM tokens found');
            $this->warn('   💡 Users need to login via mobile app first to get FCM tokens');
        } else {
            $this->info("   ✅ Total users with FCM tokens: {$totalTokens}");
        }

        // Test 6: NotificationService availability
        $this->info('6. Testing NotificationService...');
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            if ($notificationService->isFirebaseAvailable()) {
                $this->info('   ✅ NotificationService initialized and Firebase available');
            } else {
                $this->error('   ❌ NotificationService Firebase not available');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('   ❌ NotificationService error: ' . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info('🎉 Firebase Admin SDK setup test completed successfully!');
        $this->newLine();
        
        // Next steps
        $this->info('📋 Next steps:');
        if ($totalTokens > 0) {
            $this->info('   ✅ Ready to send notifications!');
            $this->info('   🧪 Test notification: php artisan notifications:test barang-terjual 22');
            $this->info('   🔍 Debug specific user: php artisan firebase:debug penitip 22');
        } else {
            $this->info('   1. Install Flutter app on real device');
            $this->info('   2. Login to app (FCM token will be saved automatically)');
            $this->info('   3. Test notification: php artisan notifications:test barang-terjual 22');
        }
        
        return 0;
    }
}