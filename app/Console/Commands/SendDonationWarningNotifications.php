<?php
// app/Console/Commands/SendDonationWarningNotifications.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReuseMartNotificationService; // ✅ USE STATEMENT INI YANG PENTING!

class SendDonationWarningNotifications extends Command
{
    protected $signature = 'notifications:donation-warning';
    protected $description = 'Send final warning notifications before items are donated';

    protected $notificationService;

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Sending donation warning notifications...');
        
        try {
            $this->notificationService->sendDonationWarningNotification();
            $this->info('Donation warning notifications sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send notifications: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}