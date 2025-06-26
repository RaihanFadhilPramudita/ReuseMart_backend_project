<?php
// app/Console/Commands/DebugFirebaseFile.php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class DebugFirebaseFile extends Command
{
    protected $signature = 'firebase:debug-file';
    protected $description = 'Debug Firebase service account file issues';

    public function handle()
    {
        $this->info('🔍 Debugging Firebase Service Account File...');
        $this->newLine();

        // Test different paths
        $paths = [
            storage_path('app/firebase/serviceAccountKey.json'),
            storage_path('app\firebase\serviceAccountKey.json'), // Windows path
            storage_path('app/firebase/serviceAccountKey'),
            storage_path('app\firebase\serviceAccountKey'),
        ];

        $this->info('1. Checking possible file paths...');
        foreach ($paths as $path) {
            $exists = file_exists($path);
            $this->info("   {$path} - " . ($exists ? '✅ EXISTS' : '❌ NOT FOUND'));
        }

        $this->newLine();
        $this->info('2. Listing files in firebase directory...');
        
        $firebaseDir = storage_path('app/firebase');
        if (is_dir($firebaseDir)) {
            $files = scandir($firebaseDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $fullPath = $firebaseDir . DIRECTORY_SEPARATOR . $file;
                    $size = filesize($fullPath);
                    $this->info("   📄 {$file} ({$size} bytes)");
                }
            }
        } else {
            $this->error("   ❌ Firebase directory doesn't exist: {$firebaseDir}");
        }

        $this->newLine();
        $this->info('3. Environment configuration...');
        $this->info('   FIREBASE_PROJECT_ID: ' . (env('FIREBASE_PROJECT_ID') ?: 'NOT SET'));
        $this->info('   FIREBASE_CREDENTIALS: ' . (env('FIREBASE_CREDENTIALS') ?: 'NOT SET'));

        $this->newLine();
        $this->info('4. Checking config values...');
        $this->info('   config("fcm.project_id"): ' . (config('fcm.project_id') ?: 'NOT SET'));
        $this->info('   config("fcm.key"): ' . (config('fcm.key') ?: 'NOT SET'));

        return 0;
    }
}