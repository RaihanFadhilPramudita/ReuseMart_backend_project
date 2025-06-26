<?php
// app/Services/NotificationService.php
namespace App\Services;

use App\Models\Notification;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class NotificationService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $credentialsPath = config('firebase.credentials.file');
            $projectId = config('firebase.project_id');

            Log::info("Firebase initialization attempt", [
                'credentials_path' => $credentialsPath,
                'project_id' => $projectId,
                'file_exists' => file_exists($credentialsPath)
            ]);

            if (!file_exists($credentialsPath)) {
                Log::error("Firebase credentials file not found at: {$credentialsPath}");
                $this->messaging = null;
                return;
            }

            if (!$projectId) {
                Log::error("Firebase project ID not configured");
                $this->messaging = null;
                return;
            }

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->withProjectId($projectId);

            $this->messaging = $factory->createMessaging();
            
            Log::info("Firebase messaging initialized successfully");
        } catch (\Exception $e) {
            Log::error("Failed to initialize Firebase messaging: " . $e->getMessage());
            $this->messaging = null;
        }
    }

    /**
     * Send notification to user using Firebase Admin SDK
     */
    public function sendNotification($userType, $userId, $type, $title, $message, $data = [])
    {
        try {
            Log::info("Sending notification", [
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

            Log::info("Notification saved to database", ['notification_id' => $notification->ID_NOTIFICATION]);

            // 2. Get user model
            $user = $this->getUser($userType, $userId);
            
            if (!$user) {
                Log::warning("User not found", ['user_type' => $userType, 'user_id' => $userId]);
                return false;
            }

            if (!$user->fcm_token) {
                Log::warning("No FCM token found for user", [
                    'user_type' => $userType,
                    'user_id' => $userId
                ]);
                return false;
            }

            Log::info("User found with FCM token", [
                'user_type' => $userType,
                'user_id' => $userId,
                'token_length' => strlen($user->fcm_token)
            ]);

            // 3. Check if Firebase messaging is initialized
            if (!$this->messaging) {
                Log::error("Firebase messaging not initialized");
                return false;
            }

            // 4. Send FCM notification using Firebase Admin SDK
            try {
                // Create Firebase notification
                $firebaseNotification = FirebaseNotification::create($title, $message);
                
                // Create cloud message
                $cloudMessage = CloudMessage::withTarget('token', $user->fcm_token)
                    ->withNotification($firebaseNotification)
                    ->withData($data);

                Log::info("Sending Firebase message", [
                    'token_preview' => substr($user->fcm_token, 0, 20) . '...'
                ]);

                // Send the message
                $result = $this->messaging->send($cloudMessage);
                
                Log::info("Firebase message sent", ['result' => $result]);

                // 5. Update notification status
                $notification->update([
                    'is_sent' => true,
                    'sent_at' => now()
                ]);

                Log::info("FCM notification sent successfully", [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'result' => $result
                ]);

                return true;

            } catch (\Kreait\Firebase\Exception\MessagingException $e) {
                Log::error("Firebase messaging exception", [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);
                return false;
            } catch (\Exception $e) {
                Log::error("General exception when sending FCM", [
                    'user_type' => $userType,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Failed to send notification", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get user model based on user type
     */
    private function getUser($userType, $userId)
    {
        try {
            switch ($userType) {
                case 'pembeli':
                    return Pembeli::find($userId);
                case 'penitip':
                    return Penitip::find($userId);
                case 'pegawai':
                    return Pegawai::find($userId);
                default:
                    Log::warning("Unknown user type", ['user_type' => $userType]);
                    return null;
            }
        } catch (\Exception $e) {
            Log::error("Error getting user", [
                'user_type' => $userType,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update FCM token for user
     */
    public function updateFcmToken($userType, $userId, $fcmToken)
    {
        try {
            switch ($userType) {
                case 'pembeli':
                    Pembeli::where('ID_PEMBELI', $userId)->update([
                        'fcm_token' => $fcmToken,
                        'fcm_token_updated_at' => now()
                    ]);
                    break;
                case 'penitip':
                    Penitip::where('ID_PENITIP', $userId)->update([
                        'fcm_token' => $fcmToken,
                        'fcm_token_updated_at' => now()
                    ]);
                    break;
                case 'pegawai':
                    Pegawai::where('ID_PEGAWAI', $userId)->update([
                        'fcm_token' => $fcmToken,
                        'fcm_token_updated_at' => now()
                    ]);
                    break;
            }
            
            Log::info("FCM token updated for {$userType} ID: {$userId}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to update FCM token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if Firebase messaging is available
     */
    public function isFirebaseAvailable()
    {
        return $this->messaging !== null;
    }

    /**
     * Send test notification
     */
    public function sendTestNotification($userType, $userId)
    {
        return $this->sendNotification(
            $userType,
            $userId,
            'test',
            '🔥 Test Notification ReuseMart',
            'Jika Anda melihat notifikasi ini, Firebase berhasil dikonfigurasi!',
            [
                'test_time' => now()->toISOString(),
                'test_type' => 'manual_test'
            ]
        );
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkNotification($userType, $userIds, $type, $title, $message, $data = [])
    {
        $successCount = 0;
        $totalCount = count($userIds);

        foreach ($userIds as $userId) {
            if ($this->sendNotification($userType, $userId, $type, $title, $message, $data)) {
                $successCount++;
            }
        }

        Log::info("Bulk notification completed: {$successCount}/{$totalCount} sent successfully");
        return $successCount;
    }
}