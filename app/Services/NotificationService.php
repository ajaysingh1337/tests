<?php

namespace App\Services;

use App\Models\Academy;
use App\Models\Devices;
use App\Models\Notifications;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Storage;

class NotificationService
{
    public static function sendNotification($user, $user_type = null, $title, $body, $deep_link, $meta = null)
    {
        Log::info('NotificationService::sendNotification called', [
            'user' => $user,
            'user_type' => $user_type,
            'title' => $title,
            'body' => $body
        ]);

        Log::info('NotificationService::sendNotification - determining user_id');
        if (is_numeric($user)) {
            $user_id = (int)$user;
            Log::info('NotificationService::sendNotification - user is numeric', ['user_id' => $user_id]);
        }
        elseif (is_object($user) && isset($user->id)) {
            $user_id = $user->id;
            Log::info('NotificationService::sendNotification - user is object', ['user_id' => $user_id]);
        }
        elseif (is_array($user) && isset($user['id'])) {
            $user_id = (int)$user['id'];
            Log::info('NotificationService::sendNotification - user is array', ['user_id' => $user_id]);
        }
        else {
            Log::error('NotificationService::sendNotification - invalid user parameter', ['user' => $user]);
            return;
        }
        $userType = null;
        Log::info('NotificationService::sendNotification - determining user type', ['user_type' => $user_type]);
        if(!isset($user_type) || empty($user_type)) {
            if ($user->relationLoaded('student') && $user->student) {
                $userType = 'student';
            } elseif ($user->relationLoaded('teacher') && $user->teacher) {
                $userType = 'teacher';
            } elseif ($user->relationLoaded('academy') && $user->academy) {
                $userType = 'academy';
            } else {
                $userType = 'user';
            }
            Log::info('NotificationService::sendNotification - determined user type from relations', ['userType' => $userType]);
        } else {
            $userType = $user_type;
            Log::info('NotificationService::sendNotification - using provided user type', ['userType' => $userType]);
        }
        if (!$userType) {
            Log::error('NotificationService::sendNotification - invalid user type parameter', ['userType' => $userType]);
            return;
        }

        $realUserId = null;
        Log::info('NotificationService::sendNotification - determining real user ID', ['userType' => $userType, 'user_id' => $user_id]);

        if($userType == 'user') {
            $realUserId = User::where('id', $user_id)->first()->id;
        } elseif($userType == 'student') {
            $realUserId = Student::where('id', $user_id)->first()->user_id;
        } elseif($userType == 'teacher') {
            $realUserId = Teacher::where('id', $user_id)->first()->user_id;
        } elseif($userType == 'academy') {
            $realUserId = Academy::where('id', $user_id)->first()->user_id;
        } else {
            Log::error('NotificationService::sendNotification - invalid user type parameter', ['userType' => $userType]);
            return;
        }
        Log::info('NotificationService::sendNotification - determined real user ID', ['realUserId' => $realUserId]);
        if (!$realUserId) {
            Log::error('NotificationService::sendNotification - invalid user parameter', ['user_id' => $user_id]);
            return;
        }

        try {
            Log::info('NotificationService::sendNotification - querying devices', ['userType' => $userType, 'realUserId' => $realUserId]);
            $devices = $userType == 'user' ? Devices::where('user_id', $realUserId)->get() : Devices::where('user_id', $realUserId)->where('user_type', $userType)->get();
            Log::info('NotificationService::sendNotification - devices query result', ['device_count' => $devices->count()]);

            if (!$devices->isEmpty()) {
                $projectId = env('VITE_FIREBASE_PROJECT_ID');
                if (!$projectId) {
                    Log::error('NotificationService::sendNotification - Firebase project ID is not configured. Please set VITE_FIREBASE_PROJECT_ID in your .env file.');
                    return;
                }
                $credentialsFileName = env('FIREBASE_CREDENTIALS_FILE_NAME');
                $credentialsFilePath = storage_path('credentials/' . $credentialsFileName);

                Log::info('Firebase credentials debug:', [
                    'credentials_file_name' => $credentialsFileName,
                    'credentials_file_path' => $credentialsFilePath,
                    'file_exists' => file_exists($credentialsFilePath),
                    'is_readable' => is_readable($credentialsFilePath),
                    'file_size' => file_exists($credentialsFilePath) ? filesize($credentialsFilePath) : 0,
                    'real_path' => realpath($credentialsFilePath)
                ]);

                if (!file_exists($credentialsFilePath)) {
                    Log::error('NotificationService::sendNotification - Firebase credentials file does not exist', ['credentialsFilePath' => $credentialsFilePath]);
                    return;
                }

                if (!is_readable($credentialsFilePath)) {
                    Log::error('NotificationService::sendNotification - Firebase credentials file is not readable', ['credentialsFilePath' => $credentialsFilePath]);
                    return;
                }

                $client = new GoogleClient();
                $client->setAuthConfig($credentialsFilePath);

                try {
                    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
                    $client->fetchAccessTokenWithAssertion();
                    $token = $client->getAccessToken();

                    Log::info('Firebase authentication successful:', [
                        'has_access_token' => isset($token['access_token']),
                        'token_length' => isset($token['access_token']) ? strlen($token['access_token']) : 0,
                        'expires_in' => $token['expires_in'] ?? null
                    ]);

                    $access_token = $token['access_token'];

                    $headers = [
                        "Authorization: Bearer $access_token",
                        'Content-Type: application/json'
                    ];

                    $processedCount = 0;
                    $failedCount = 0;

                    foreach ($devices as $device) {
                        try {
                            self::sendNotificationToDevice($device, $userType, $headers, $projectId, $title, $body, $deep_link, $meta);
                            $processedCount++;
                        } catch (\Exception $e) {
                            $failedCount++;
                            Log::error('Failed to send notification to device:', [
                                'device_id' => $device->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    Log::info('Notification batch completed:', [
                        'total_devices' => $devices->count(),
                        'successful_sends' => $processedCount,
                        'failed_sends' => $failedCount,
                        'user_type' => $userType,
                        'title' => $title
                    ]);
                } catch (\Exception $e) {
                    Log::error('Firebase authentication failed:', [
                        'error' => $e->getMessage(),
                        'credentials_file_path' => $credentialsFilePath,
                        'trace' => $e->getTraceAsString()
                    ]);
                    return;
                }
            }
        } catch (\Exception $e) {
            Log::error('Error sending fcm notification:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return;
        }

        try {
            $notification = Notifications::create([
                'user_id' => $realUserId,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body,
                'action' => $deep_link,
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating notification:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $realUserId ?? null,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body
            ]);
            return;
        }
    }

    protected static function sendNotificationToDevice($device, $userType, $headers, $projectId, $title, $body, $deep_link, $meta = null)
    {
        $token = $device->token;
        $deviceId = $device->id;

        Log::info('NotificationService::sendNotificationToDevice - sending to device', [
            'device_id' => $deviceId,
            'token' => $token,
            'user_type' => $userType,
            'title' => $title
        ]);

        $data = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => [
                    "user_type" => $userType,
                    "click_action" => $deep_link,
                    ...(isset($meta) ? ["meta"=> json_encode($meta)] : [])
                ],
                ...(isset($deep_link) ? ["webpush"=> [
                    "fcm_options"=> [
                        "link"=> $deep_link
                    ]
                ]] : [])
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            Log::error('Error sending notification:', [
                'error' => $err,
                'device_id' => $deviceId,
                'token' => $token,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body
            ]);
            return; // Continue processing other devices
        }
        
        $responseData = json_decode($response, true);
        
        // Check if there's an error in the Firebase response
        if (isset($responseData['error'])) {
            $errorCode = $responseData['error']['code'] ?? 'UNKNOWN';
            $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
            
            Log::error('Firebase API Error:', [
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'device_id' => $deviceId,
                'token' => $token,
                'user_type' => $userType,
                'title' => $title,
                'body' => $body,
                'response' => $responseData
            ]);

            // Remove invalid tokens from database for any 400-level error
            if ($errorCode >= 400 && $errorCode < 500) {
                // Remove the invalid device token from database
                Devices::where('id', $deviceId)->delete();
                Log::info('Removed invalid device token from database:', [
                    'device_id' => $deviceId,
                    'token' => $token,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'reason' => 'Client error - invalid token'
                ]);
            }

            return; // Continue processing other devices
        }
        
        Log::info('Notification sent successfully to device:', [
            'device_id' => $deviceId,
            'token' => $token,
            'user_type' => $userType,
            'title' => $title
        ]);
    }
}
