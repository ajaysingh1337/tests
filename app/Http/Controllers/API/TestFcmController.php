<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\FCM\GCMService;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class TestFcmController extends Controller
{
    protected $gcmService;

    public function __construct(GCMService $gcmService)
    {
        $this->gcmService = $gcmService;
    }

    /**
     * Test student login with FCM token registration
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function studentLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'fcm_token' => 'required|string',
            'device_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find student by email
        $student = Student::where('email', $request->email)->first();

        if (!$student || !Hash::check($request->password, $student->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Register device with FCM
        $this->gcmService->registerDevice(
            $student->id,
            'student',
            $request->fcm_token,
            $request->device_id,
            $request->only(['device_name', 'os'])
        );

        // Send test notification
        $notificationResult = $this->gcmService->sendToUser(
            'student',
            $student->id,
            'Welcome to Tutor App',
            'You have successfully logged in!',
            ['type' => 'test_notification']
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful and test notification sent',
            'student' => $student->only(['id', 'name', 'email']),
            'notification_sent' => $notificationResult['success'] ?? false,
            'notification_details' => $notificationResult
        ]);
    }
}
