<?php
// app/Console/Commands/SendMasaPenitipanNotifications.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReuseMartNotificationService; // ✅ USE STATEMENT INI YANG PENTING!
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class SendMasaPenitipanNotifications extends Command
{
    protected $signature = 'notifications:masa-penitipan';
    protected $description = 'Send notifications for items nearing end of consignment period';

    protected $notificationService;

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle()
    {
        $this->info('Sending masa penitipan notifications...');
        
        try {
            $this->notificationService->sendMasaPenitipanNotification();
            $this->info('Masa penitipan notifications sent successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to send notifications: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}