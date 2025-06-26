<?php
// tests/Feature/NotificationTest.php - Unit Testing
namespace Tests\Feature;

use Tests\TestCase;
use App\Services\NotificationService;
use App\Services\ReuseMartNotificationService;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Barang;
use App\Models\Penitipan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $notificationService;
    protected $reusemartNotificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
        $this->reusemartNotificationService = new ReuseMartNotificationService($this->notificationService);
    }

    /** @test */
    public function it_can_send_basic_notification()
    {
        $pembeli = Pembeli::factory()->create(['fcm_token' => 'test_fcm_token']);
        
        $result = $this->notificationService->sendNotification(
            'pembeli',
            $pembeli->ID_PEMBELI,
            'test',
            'Test Title',
            'Test Message',
            ['test_data' => 'value']
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('notifications', [
            'user_type' => 'pembeli',
            'user_id' => $pembeli->ID_PEMBELI,
            'title' => 'Test Title',
            'message' => 'Test Message'
        ]);
    }

    /** @test */
    public function it_can_send_masa_penitipan_notification()
    {
        $penitip = Penitip::factory()->create(['fcm_token' => 'test_token']);
        $penitipan = Penitipan::factory()->create([
            'ID_PENITIP' => $penitip->ID_PENITIP,
            'TANGGAL_KADALUARSA' => now()->addDays(3)
        ]);

        $this->reusemartNotificationService->sendMasaPenitipanNotification();

        $this->assertDatabaseHas('notifications', [
            'user_type' => 'penitip',
            'user_id' => $penitip->ID_PENITIP,
            'type' => 'masa_penitipan'
        ]);
    }

    /** @test */
    public function it_can_send_barang_terjual_notification()
    {
        $penitip = Penitip::factory()->create(['fcm_token' => 'test_token']);
        $barang = Barang::factory()->create(['ID_PENITIP' => $penitip->ID_PENITIP]);

        $result = $this->reusemartNotificationService->sendBarangTerjualNotification($barang->ID_BARANG);

        $this->assertTrue($result);
        $this->assertDatabaseHas('notifications', [
            'user_type' => 'penitip',
            'user_id' => $penitip->ID_PENITIP,
            'type' => 'barang_terjual'
        ]);
    }
}

// app/Console/Commands/TestNotifications.php - Testing Command
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReuseMartNotificationService;
use App\Models\Pembeli;
use App\Models\Penitip;

class TestNotifications extends Command
{
    protected $signature = 'notifications:test {type} {user_id?}';
    protected $description = 'Test notification sending';

    protected $notificationService;

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $type = $this->argument('type');
        $userId = $this->argument('user_id');

        switch ($type) {
            case 'masa-penitipan':
                $this->info('Testing masa penitipan notifications...');
                $this->notificationService->sendMasaPenitipanNotification();
                break;

            case 'donation-warning':
                $this->info('Testing donation warning notifications...');
                $this->notificationService->sendDonationWarningNotification();
                break;

            case 'barang-terjual':
                if (!$userId) {
                    $this->error('User ID required for barang-terjual test');
                    return 1;
                }
                $this->info("Testing barang terjual notification for barang ID: {$userId}");
                $result = $this->notificationService->sendBarangTerjualNotification($userId);
                $this->info($result ? 'Success!' : 'Failed!');
                break;

            case 'status-pengiriman':
                if (!$userId) {
                    $this->error('Transaksi ID required for status-pengiriman test');
                    return 1;
                }
                $this->info("Testing status pengiriman notification for transaksi ID: {$userId}");
                $result = $this->notificationService->sendStatusPengirimanNotification($userId, 'sedang diantar');
                $this->info($result ? 'Success!' : 'Failed!');
                break;

            default:
                $this->error('Invalid notification type. Available types: masa-penitipan, donation-warning, barang-terjual, status-pengiriman');
                return 1;
        }

        return 0;
    }
}

// Deployment checklist and commands
/*
DEPLOYMENT CHECKLIST:

1. Environment Setup:
   - Set up Firebase project
   - Get FCM server key and add to .env
   - Upload service account key file
   - Configure Firebase settings

2. Database Migration:
   php artisan migrate

3. Install Dependencies:
   composer install --no-dev --optimize-autoloader

4. Setup Cron Job (for scheduled notifications):
   Add to crontab:
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1

5. Queue Worker (if using queues):
   php artisan queue:work --daemon

6. Test Commands:
   php artisan notifications:test masa-penitipan
   php artisan notifications:test barang-terjual 1
   php artisan notifications:test status-pengiriman 1

7. Monitor Logs:
   tail -f storage/logs/laravel.log

8. Production Settings:
   - Set APP_ENV=production
   - Set APP_DEBUG=false
   - Configure proper logging
   - Set up monitoring (Sentry, etc.)

MANUAL TESTING STEPS:

1. Test FCM Token Registration:
   - Login to mobile app
   - Check if FCM token is saved in database
   - Check logs for token registration

2. Test Notification Sending:
   - Use test commands above
   - Check database for notification records
   - Check mobile device for received notifications

3. Test Notification Interaction:
   - Tap notifications on mobile
   - Verify correct navigation/actions
   - Test mark as read functionality

4. Test Scheduled Notifications:
   - Manually run scheduler: php artisan schedule:run
   - Check logs for execution
   - Verify notifications are sent

5. Load Testing:
   - Test with multiple users
   - Test bulk notification sending
   - Monitor performance and memory usage

MONITORING & MAINTENANCE:

1. Database Cleanup:
   - Regularly clean old notifications
   - Monitor database size growth

2. FCM Token Management:
   - Handle token refresh
   - Clean invalid tokens
   - Monitor delivery rates

3. Error Handling:
   - Monitor failed notifications
   - Set up alerts for service failures
   - Log all notification activities

4. Performance Monitoring:
   - Track notification delivery times
   - Monitor Firebase quota usage
   - Track user engagement with notifications
*/