<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct()
    {
        // Initialize service carefully
        try {
            if (class_exists('\App\Services\NotificationService')) {
                $this->notificationService = app(NotificationService::class);
            } else {
                $this->notificationService = null;
            }
        } catch (\Exception $e) {
            Log::error('Failed to initialize NotificationService: ' . $e->getMessage());
            $this->notificationService = null;
        }
    }

    /**
     * Update FCM token for authenticated user (auto-detects user type)
     */
    public function updateFcmToken(Request $request)
    {
        Log::info('FCM token update request received', [
            'user_agent' => $request->header('User-Agent'),
            'content_type' => $request->header('Content-Type'),
            'auth_header' => $request->header('Authorization') ? 'Present' : 'Missing'
        ]);

        try {
            $request->validate([
                'fcm_token' => 'required|string|min:50'
            ]);

            $user = $request->user();
            
            if (!$user) {
                Log::warning('FCM token update: No authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Detect user type and ID based on authenticated model
            $userType = $this->detectUserType($user);
            $userId = $this->getUserId($user, $userType);

            if (!$userType || !$userId) {
                Log::error('FCM token update: Unable to determine user type', [
                    'user_class' => get_class($user),
                    'user_id_fields' => array_keys($user->getAttributes())
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine user type'
                ], 400);
            }

            Log::info("FCM Token update request", [
                'user_type' => $userType,
                'user_id' => $userId,
                'token_length' => strlen($request->fcm_token)
            ]);

            // Update FCM token directly in database (simpler approach)
            $success = $this->updateFcmTokenDirect($userType, $userId, $request->fcm_token);

            if ($success) {
                Log::info("FCM token updated successfully for {$userType} ID: {$userId}");
                return response()->json([
                    'success' => true,
                    'message' => 'FCM token updated successfully',
                    'user_type' => $userType,
                    'user_id' => $userId
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token'
            ], 500);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('FCM token validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('FCM token update error: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update FCM token directly in database (bypass service layer)
     */
    private function updateFcmTokenDirect($userType, $userId, $fcmToken)
    {
        try {
            $updateData = [
                'fcm_token' => $fcmToken,
                'fcm_token_updated_at' => now()
            ];

            switch ($userType) {
                case 'pembeli':
                    $affected = Pembeli::where('ID_PEMBELI', $userId)->update($updateData);
                    break;
                case 'penitip':
                    $affected = Penitip::where('ID_PENITIP', $userId)->update($updateData);
                    break;
                case 'pegawai':
                    $affected = Pegawai::where('ID_PEGAWAI', $userId)->update($updateData);
                    break;
                default:
                    return false;
            }
            
            Log::info("FCM token updated directly in database", [
                'user_type' => $userType,
                'user_id' => $userId,
                'affected_rows' => $affected
            ]);
            
            return $affected > 0;
            
        } catch (\Exception $e) {
            Log::error("Failed to update FCM token directly: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Specific method for Pembeli FCM token (backward compatibility)
     */
    public function updatePembeliFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = $request->user();
        if (!isset($user->ID_PEMBELI)) {
            return response()->json(['error' => 'User bukan pembeli'], 403);
        }

        $success = $this->updateFcmTokenDirect('pembeli', $user->ID_PEMBELI, $request->fcm_token);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'FCM token updated' : 'Failed to update FCM token'
        ]);
    }

    /**
     * Specific method for Penitip FCM token (backward compatibility)
     */
    public function updatePenitipFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = $request->user();
        if (!isset($user->ID_PENITIP)) {
            return response()->json(['error' => 'User bukan penitip'], 403);
        }

        $success = $this->updateFcmTokenDirect('penitip', $user->ID_PENITIP, $request->fcm_token);

        return response()->json([
            'success' => true,
            'message' => $success ? 'FCM token updated' : 'Failed to update FCM token'
        ]);
    }

    /**
     * Specific method for Pegawai FCM token (backward compatibility)
     */
    public function updatePegawaiFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);

        $user = $request->user();
        if (!isset($user->ID_PEGAWAI)) {
            return response()->json(['error' => 'User bukan pegawai'], 403);
        }

        $success = $this->updateFcmTokenDirect('pegawai', $user->ID_PEGAWAI, $request->fcm_token);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'FCM token updated' : 'Failed to update FCM token'
        ]);
    }

    /**
     * Get notifications for authenticated user
     */
    public function getUserNotifications(Request $request)
    {
        try {
            $user = $request->user();
            $userType = $this->detectUserType($user);
            $userId = $this->getUserId($user, $userType);

            $notifications = Notification::where('user_type', $userType)
                                       ->where('user_id', $userId)
                                       ->orderBy('created_at', 'desc')
                                       ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId)
    {
        try {
            $user = request()->user();
            $userType = $this->detectUserType($user);
            $userId = $this->getUserId($user, $userType);

            $notification = Notification::where('ID_NOTIFICATION', $notificationId)
                                      ->where('user_type', $userType)
                                      ->where('user_id', $userId)
                                      ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount()
    {
        try {
            $user = request()->user();
            $userType = $this->detectUserType($user);
            $userId = $this->getUserId($user, $userType);

            $count = Notification::where('user_type', $userType)
                                ->where('user_id', $userId)
                                ->where('is_read', false)
                                ->count();

            return response()->json([
                'success' => true,
                'unread_count' => $count
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detect user type from authenticated user model
     */
    private function detectUserType($user)
    {
        $className = class_basename($user);
        
        switch ($className) {
            case 'Pembeli':
                return 'pembeli';
            case 'Penitip':
                return 'penitip';
            case 'Pegawai':
                return 'pegawai';
            default:
                Log::warning('Unknown user type detected', ['class' => $className]);
                throw new \Exception('Unknown user type: ' . $className);
        }
    }

    /**
     * Get user ID based on user type
     */
    private function getUserId($user, $userType)
    {
        switch ($userType) {
            case 'pembeli':
                return $user->ID_PEMBELI ?? null;
            case 'penitip':
                return $user->ID_PENITIP ?? null;
            case 'pegawai':
                return $user->ID_PEGAWAI ?? null;
            default:
                throw new \Exception('Unknown user type: ' . $userType);
        }
    }
}