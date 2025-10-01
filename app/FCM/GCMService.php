<?php

namespace App\FCM;

use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\Devices;
use App\Models\Notifications as NotificationModel;
use Illuminate\Support\Facades\Log;

class GCMService
{
    protected $messaging;

    public function __construct()
    {
     //   $this->messaging = Firebase::messaging();
    }

    /**
     * Register a device token for a user
     * 
     * @param int $userId
     * @param string $userType (student, teacher, academy)
     * @param string $token
     * @param string|null $deviceId
     * @param array|null $deviceInfo
     * @return Devices
     */
    public function registerDevice($userId, $userType, $token, $deviceId = null, $deviceInfo = null)
    {
        try {
            Log::info('Registering device', [
                'user_id' => $userId,
                'user_type' => $userType,
                'token' => $token ? substr($token, 0, 10) . '...' : 'null',
                'device_id' => $deviceId
            ]);

            if (empty($token)) {
                throw new \InvalidArgumentException('FCM token cannot be empty');
            }

            // Check if token already exists
            $existingToken = Devices::where('token', $token)->first();
            
            if ($existingToken) {
                Log::info('Updating existing device token', ['id' => $existingToken->id]);
                // Update existing token
                $existingToken->update([
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'device_id' => $deviceId,
                    'device_info' => $deviceInfo ? json_encode($deviceInfo) : null,
                ]);
                return $existingToken;
            }

            Log::info('Creating new device token');
            // Create new token
            $device = Devices::create([
                'user_id' => $userId,
                'user_type' => $userType,
                'token' => $token,
                'device_id' => $deviceId,
                'device_info' => $deviceInfo ? json_encode($deviceInfo) : null,
            ]);

            Log::info('Device registered successfully', ['id' => $device->id]);
            return $device;

        } catch (\Exception $e) {
            Log::error('Failed to register device', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Unregister a device token
     * 
     * @param string $token
     * @return bool
     */
    public function unregisterDevice($token)
    {
        return Devices::where('token', $token)->delete() > 0;
    }

    /**
     * Send notification to a specific user
     * 
     * @param string $userType
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string|null $deviceToken If null, send to all user's devices
     * @return array
     */
    public function sendToUser($userType, $userId, $title, $body, $data = [], $deviceToken = null)
    {
        $tokens = $deviceToken 
            ? Devices::where('token', $deviceToken)
                ->where('user_id', $userId)
                ->where('user_type', $userType)
                ->pluck('token')
                ->toArray()
            : Devices::where('user_id', $userId)
                ->where('user_type', $userType)
                ->pluck('token')
                ->toArray();

        if (empty($tokens)) {
            return [
                'success' => false,
                'message' => 'No device tokens found for the user'
            ];
        }

        $notification = Notification::fromArray([
            'title' => $title,
            'body' => $body,
        ]);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        try {
            $sendReport = $this->messaging->sendMulticast($message, $tokens);
            $results = $sendReport->getItems();
            
            foreach ($results as $result) {
                // Check if message was sent successfully by checking if it's an instance of SendResponse
                if ($result instanceof \Kreait\Firebase\Messaging\SendReport) {
                    if ($result->isSuccess()) {
                        $successCount++;
                    } else {
                        $failureCount++;
                        $error = $result->target()->error() ? 
                            $result->target()->error()->getMessage() : 'Unknown error';
                        Log::warning('Failed to send FCM message', ['error' => $error]);
                    }
                } else {
                    $failureCount++;
                    Log::warning('Failed to send FCM message', ['error' => 'Invalid response type']);
                }
            }
        } catch (\Exception $e) {
            Log::error('FCM Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        // Save notification to database
        if ($successCount > 0) {
            $this->saveNotification($userType, $userId, $title, $body, $data);
        }

        return [
            'success' => true,
            'sent_count' => $successCount,
            'failed_count' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Save notification to database
     */
    protected function saveNotification($notifiableType, $notifiableId, $title, $body, $data = [])
    {
        return NotificationModel::create([
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'type' => get_class($this),
            'data' => json_encode([
                'title' => $title,
                'body' => $body,
                'data' => $data
            ])
        ]);
    }

    /**
     * Send a message to a specific device
     * 
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToDevice($token, $title, $body, $data = [])
    {
        $notification = Notification::fromArray([
            'title' => $title,
            'body' => $body,
        ]);

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        try {
            $messageId = $this->messaging->send($message, $token);
            
            // Find user info from token
            $fcmToken = Devices::where('token', $token)->first();
            if ($fcmToken) {
                $this->saveNotification(
                    $fcmToken->user_type,
                    $fcmToken->user_id,
                    $title,
                    $body,
                    $data
                );
            }

            return [
                'success' => true,
                'message_id' => $messageId
            ];
        } catch (\Exception $e) {
            Log::error('FCM Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}