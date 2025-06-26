<?php
// app/Services/FCMService.php
namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class FCMService
{
    private $projectId;
    private $serviceAccountPath;

    public function __construct()
    {
        $this->projectId = config('fcm.project_id');
        $this->serviceAccountPath = config('fcm.service_account_path');
    }

    /**
     * Get OAuth2 access token from service account
     */
    private function getAccessToken()
    {
        try {
            if (!file_exists($this->serviceAccountPath)) {
                throw new \Exception("Service account file not found: {$this->serviceAccountPath}");
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                json_decode(file_get_contents($this->serviceAccountPath), true)
            );

            $httpHandler = HttpHandlerFactory::build();
            $token = $credentials->fetchAuthToken($httpHandler);

            return $token['access_token'];
        } catch (\Exception $e) {
            Log::error("Failed to get FCM access token: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send FCM notification
     */
    public function sendNotification($fcmToken, $title, $body, $data = [])
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $message = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $body
                    ],
                    'data' => array_map('strval', $data), // FCM requires string values
                    'android' => [
                        'notification' => [
                            'color' => config('fcm.default_color'),
                            'sound' => config('fcm.default_sound'),
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json'
            ])->post($url, $message);

            if ($response->successful()) {
                Log::info("FCM notification sent successfully", [
                    'token' => substr($fcmToken, 0, 20) . '...',
                    'title' => $title
                ]);
                return true;
            } else {
                Log::error("FCM notification failed", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'token' => substr($fcmToken, 0, 20) . '...'
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error("FCM Service Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple tokens
     */
    public function sendMulticast($tokens, $title, $body, $data = [])
    {
        $results = [];
        foreach ($tokens as $token) {
            $results[] = $this->sendNotification($token, $title, $body, $data);
        }
        return $results;
    }

    /**
     * Validate FCM token format
     */
    public function validateToken($token)
    {
        return !empty($token) && is_string($token) && strlen($token) > 50;
    }
}