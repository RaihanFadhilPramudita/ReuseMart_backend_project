<?php
// app/Services/NotificationService.php - Updated untuk FCM v1
namespace App\Services;

use App\Models\Notification;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Pegawai;
use App\Services\CleanFCMService;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected $fcmService;

    public function __construct(CleanFCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send notification to user using FCM v1 API
     */
    public function sendNotification($userType, $userId, $type, $title, $message, $data = [])
    {
        try {
            Log::info("🔔 Sending FCM v1 notification", [
                'user_type' => $userType,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title
            ]);

            // 1. Save notification to database
            $notification = Notification::create([
                'user_type' => $userType,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_sent' => false
            ]);

            // 2. Get user model
            $user = $this->getUser($userType, $userId);
            
            if (!$user) {
                Log::warning("❌ User not found: {$userType} ID: {$userId}");
                return false;
            }

            if (!$user->fcm_token) {
                Log::warning("❌ No FCM token found for {$userType} ID: {$userId}");
                return false;
            }

            // 3. Send FCM notification using v1 API
            try {
                $success = $this->fcmService->sendToDevice(
                    $user->fcm_token,
                    $title,
                    $message,
                    array_merge($data, [
                        'notification_id' => (string)$notification->ID_NOTIFICATION,
                        'user_type' => $userType,
                        'user_id' => (string)$userId,
                        'timestamp' => now()->toISOString(),
                    ])
                );
                
                // 4. Update notification status
                $notification->update([
                    'is_sent' => $success,
                    'sent_at' => $success ? now() : null
                ]);

                if ($success) {
                    Log::info("✅ FCM v1 notification sent successfully to {$userType} ID: {$userId}");
                } else {
                    Log::error("❌ FCM v1 notification failed for {$userType} ID: {$userId}");
                }

                return $success;

            } catch (\Exception $e) {
                Log::error("❌ FCM v1 notification exception for {$userType} ID: {$userId} - " . $e->getMessage());
                
                // Update notification as failed
                $notification->update([
                    'is_sent' => false,
                    'sent_at' => null
                ]);
                
                return false;
            }

        } catch (\Exception $e) {
            Log::error("❌ Failed to send FCM v1 notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user model based on user type
     */
    private function getUser($userType, $userId)
    {
        switch ($userType) {
            case 'pembeli':
                return Pembeli::find($userId);
            case 'penitip':
                return Penitip::find($userId);
            case 'pegawai':
                return Pegawai::find($userId);
            default:
                return null;
        }
    }

    /**
     * Update FCM token for user
     */
    public function updateFcmToken($userType, $userId, $fcmToken)
    {
        try {
            $updateData = [
                'fcm_token' => $fcmToken,
                'fcm_token_updated_at' => now()
            ];

            switch ($userType) {
                case 'pembeli':
                    Pembeli::where('ID_PEMBELI', $userId)->update($updateData);
                    break;
                case 'penitip':
                    Penitip::where('ID_PENITIP', $userId)->update($updateData);
                    break;
                case 'pegawai':
                    Pegawai::where('ID_PEGAWAI', $userId)->update($updateData);
                    break;
                default:
                    Log::error("❌ Invalid user type for FCM token update: {$userType}");
                    return false;
            }
            
            Log::info("✅ FCM token updated for {$userType} ID: {$userId}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("❌ Failed to update FCM token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send bulk notifications efficiently
     */
    public function sendBulkNotification($userType, $userIds, $type, $title, $message, $data = [])
    {
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($userIds as $userId) {
            if ($this->sendNotification($userType, $userId, $type, $title, $message, $data)) {
                $successCount++;
            } else {
                $failedCount++;
            }
            
            // Add small delay to prevent rate limiting
            usleep(100000); // 0.1 second delay
        }

        Log::info("📊 Bulk FCM v1 notification completed", [
            'successful' => $successCount,
            'failed' => $failedCount,
            'total' => count($userIds)
        ]);
        
        return [
            'successful' => $successCount,
            'failed' => $failedCount,
            'total' => count($userIds)
        ];
    }

    /**
     * Send notification to topic using v1 API
     */
    public function sendTopicNotification($topic, $title, $message, $data = [])
    {
        try {
            Log::info("📢 Sending FCM v1 topic notification to: {$topic}");
            
            $success = $this->fcmService->sendToTopic($topic, $title, $message, $data);
            
            if ($success) {
                Log::info("✅ FCM v1 topic notification sent successfully to: {$topic}");
            } else {
                Log::error("❌ Failed to send FCM v1 topic notification to: {$topic}");
            }

            return $success;

        } catch (\Exception $e) {
            Log::error("❌ FCM v1 topic notification exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Subscribe users to topic
     */
    public function subscribeUsersToTopic($userType, $userIds, $topic)
    {
        try {
            $fcmTokens = [];
            
            foreach ($userIds as $userId) {
                $user = $this->getUser($userType, $userId);
                if ($user && $user->fcm_token) {
                    $fcmTokens[] = $user->fcm_token;
                }
            }
            
            if (empty($fcmTokens)) {
                Log::warning("❌ No FCM tokens found for topic subscription");
                return false;
            }
            
            $success = $this->fcmService->subscribeToTopic($fcmTokens, $topic);
            
            Log::info("📋 Topic subscription result", [
                'topic' => $topic,
                'tokens_count' => count($fcmTokens),
                'success' => $success
            ]);
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error("❌ Topic subscription exception: " . $e->getMessage());
            return false;
        }
    }
}