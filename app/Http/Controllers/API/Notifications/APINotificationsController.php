<?php

namespace App\Http\Controllers\API\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Devices;
use App\Models\Notifications;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Academy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class APINotificationsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['api', 'auth:api']);
    }
    /**
     * Save FCM token for the authenticated user
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveFcmToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'device_id' => 'required|string',
                'device_info' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                $response = generateResponse(null, false, $validator->errors()->first(), null, 'collection');
                return response()->json($response, 422);
            }

            $user = auth()->user();

            if (!$user) {
                $response = generateResponse(null, false, 'Unauthorized', null, 'collection');
                return response()->json($response, 401);
            }

            // Determine user type based on the current login mode (student or teacher relationship in the user object)
            // Get user type from header first, then fall back to session
            $userType = $request->header('logged-in-as') ?? $request->session()->get('logged_in_as');

            if (!$userType) {
                $response = generateResponse(null, false, 'User type could not be determined. Please log in with a valid role (student/teacher/academy).', null, 'collection');
                return response()->json($response, 400);
            }

            $userType = strtolower($userType);

            // Check if device with this user_id, user_type, and device_id combination exists
            $device = Devices::where('user_id', $user->id)
                ->where('user_type', $userType)
                ->where('device_id', $request->device_id)
                ->first();

            if ($device) {
                // Update existing device with new token and device info
                $device->update([
                    'token' => $request->token,
                    'device_info' => $request->device_info ?? $device->device_info,
                ]);
            } else {
                // Create new device
                $device = Devices::create([
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'token' => $request->token,
                    'device_id' => $request->device_id,
                    'device_info' => $request->device_info,
                ]);
            }

            $response = generateResponse($device, true, 'FCM token saved successfully', null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 500);
        }
    }

    /**
     * Get all notifications for the authenticated user
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNotifications(Request $request)
    {
        try {
            $user = auth()->user();
            $userType = $request->header('logged-in-as') ?? $request->session()->get('logged_in_as');

            if (!$userType) {
                throw new \Exception('User type could not be determined');
            }

            $userType = strtolower($userType);

            $perPage = $request->per_page ?? 15;
            $page = $request->page ?? 1;

            $notifications = Notifications::where('user_id', $user->id)->where('user_type', $userType)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $unread_count = Notifications::where('user_id', $user->id)->where('user_type', $userType)->where("read_at", null)->count();
            
            $response = generateResponse(["notifications" => $notifications, "unread_count" => $unread_count], true, 'Notifications retrieved successfully', null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 500);
        }
    }

    /**
     * Mark notifications as read
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  int|null  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id = null)
    {
        try {
            $user = auth()->user();
            $userType = $request->header('logged-in-as') ?? $request->session()->get('logged_in_as');

            if (!$userType) {
                throw new \Exception('User type could not be determined');
            }

            $userType = strtolower($userType);

            if ($id) {
                // Mark specific notification as read
                $notification = Notifications::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('user_type', $userType)
                    ->firstOrFail();

                $notification->markAsRead();
                $message = 'Notification marked as read';
            } else {
                // Mark all notifications as read
                $count = Notifications::where('user_id', $user->id)
                    ->where('user_type', $userType)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);

                $message = "$count notifications marked as read";
            }

            $response = generateResponse(null, true, $message, null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 500);
        }
    }

    public function testNotification(Request $request)
    {
        try {
            $user = auth()->user();
            \App\Services\NotificationService::sendNotification($user, 'user', 'Test Notification', 'This is a test notification', 'pages/about');
            $response = generateResponse(null, true, 'Notification sent successfully', null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 500);
        }
    }
}
