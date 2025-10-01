<?php

namespace App\Http\Controllers\API\Teachers;

use App\Http\Controllers\API\WalletController;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentController;
use App\Http\Requests\Teachers\UpdateLiveAvailabilityRequest;
use App\Http\Resources\API\AppointmentSchedulesResource;
use App\Http\Resources\API\AppointmentTypesResource;
use App\Http\Resources\API\TeachersResource;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\BookAppointment;
use App\Models\LiveRequest;
use App\Models\Teacher;
use App\Models\TeacherLiveAvailability;
use App\Services\NotificationService;
use App\Services\SocketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class APITeacherProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['getLwyerAppointmentTypes']);
    }

    public function bookAppointment(Request $request, $user_name)
    {
        try {
            $request->validate([
                'appointment_type_id' => 'required|exists:appointment_types,id',
                'date' => 'required|date',
            ]);
            $teacher = Teacher::where('user_name', $user_name)->first();
            if (!$teacher) {
                $response = generateResponse(null, false, 'Teacher Not Found', null, 'collection');
                return response()->json($response, 404);
            }
            $teacher_id = $teacher->id;
            $appointment_type = AppointmentType::select('id', 'is_schedule_required')->where('id', $request->appointment_type_id)->first();
            $day = Carbon::parse($request->date)->format('l');
            if ($appointment_type->is_schedule_required) {
                $schedule = AppointmentSchedule::with('appointment_type')->with('schedule_slots')->where('teacher_id', $teacher_id)->where('appointment_type_id', $appointment_type->id)->where('day', $day)->first();
            } else {
                $schedule = AppointmentSchedule::with('appointment_type')->with('schedule_slots')->where('teacher_id', $teacher_id)->where('appointment_type_id', $appointment_type->id)->first();
            }
            if ($schedule) {
                $scheduleSlots = $schedule->schedule_slots;
                if (count($scheduleSlots) > 0) {
                    foreach ($scheduleSlots as $scheduleSlot) {
                        $is_disabled = BookAppointment::where('teacher_id', $teacher_id)
                            ->whereDate('date', $request->date)
                            ->where('is_paid', 1)
                            ->where(function ($q) use ($scheduleSlot) {
                                $q->where(function ($z) use ($scheduleSlot) {
                                    $z->where('start_time', $scheduleSlot->start_time);
                                    $z->where('end_time', $scheduleSlot->end_time);
                                });
                            })->count();

                        $scheduleSlot['is_disabled'] = $is_disabled;
                    }
                }
                $schedule = new AppointmentSchedulesResource($schedule);
            } else {
                $schedule = null;
            }
            $data['schedule'] = $schedule;
            $response = generateResponse($data, true, 'Data Fetched Successfully', null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }

    public function getLawyerAppointmentTypes(Request $request, $user_name)
    {
        try {
            Log::info($user_name);
            $teacher = Teacher::withAll()->where('user_name', $user_name)->first();
            if (!$teacher) {
                $response = generateResponse(null, false, 'Teacher Not Found', null, 'collection');
                return response()->json($response, 404);
            }
            $appointment_types = $teacher->appointment_schedules()->pluck('appointment_type_id')->toArray();
            $appointment_types = AppointmentType::whereIn('id', array_unique($appointment_types))->active()->get();
            $appointment_types = AppointmentTypesResource::collection($appointment_types);
            $response = generateResponse($appointment_types, true, 'Data Fetched Successfully', null, 'collection');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }

    public function updateLiveAvailability(UpdateLiveAvailabilityRequest $request)
    {
        try {
            $teacher = auth()->user()->teacher;
            if ($request->latitude && $request->longitude) {
                $teacher->latitude = $request->latitude;
                $teacher->longitude = $request->longitude;
                $teacher->save();
            }

            $live = TeacherLiveAvailability::where('teacher_id', $teacher->id)->first();

            $updateData = [
                'status' => $request->status,
                'updated_at' => now()
            ];

            if ($request->filled('start_time')) {
                $updateData['start_time'] = $request->start_time;
            }

            if ($request->filled('end_time')) {
                $updateData['end_time'] = $request->end_time;
            }

            if ($request->filled('fee')) {
                $updateData['fee'] = $request->fee;
            }

            if ($live) {
                $live->update($updateData);
            } else {
                $updateData['teacher_id'] = $teacher->id;
                $updateData['created_at'] = now();
                TeacherLiveAvailability::create($updateData);
            }

            return generateResponse(null, true, 'Live Availability Updated Successfully', null, 'collection');
        } catch (\Exception $e) {
            Log::error($e);
            return generateResponse(null, false, 'Live Availability Update Failed', null, 'collection');
        }
    }

    public function getLiveAvailability()
    {
        try {
            $teacher = auth()->user()->teacher;
            $live = TeacherLiveAvailability::where('teacher_id', $teacher->id)->first();
            return generateResponse($live, true, 'Live Availability Fetched Successfully', null, 'collection');
        } catch (\Exception $e) {
            Log::error($e);
            return generateResponse(null, false, 'Live Availability Fetch Failed', null, 'collection');
        }
    }

    public function searchByRadius(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'current_time' => 'required|date',
            'duration' => 'sometimes|integer|min:1',
            'category' => 'sometimes|exists:teacher_categories,id',
            'radius' => 'sometimes|numeric|min:1',
            'unit' => 'sometimes|in:km,mi'
        ]);

        Log::info($request->all());

        try {
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $radius = $request->input('radius', 10);
            $unit = $request->input('unit', 'km');
            $categoryId = $request->input('category');
            $currentTime = Carbon::parse($request->input('current_time'));
            $duration = (int) $request->input('duration', 30); // Default to 30 minutes if not provided

            // Build the base query
            $query = Teacher::with(['user', 'live_availability', 'teacher_categories'])
                ->whereHas('live_availability', function ($q) use ($currentTime, $duration) {
                    // Filter out offline and in-call statuses
                    $q->where('status', 'online')
                        // Check if current time is within the availability window
                        ->where('start_time', '<=', $currentTime)
                        // Check if the requested duration fits within the availability window
                        ->where('end_time', '>=', (clone $currentTime)->modify("+{$duration} minutes"));
                });

            // Filter by category if provided
            if ($categoryId) {
                $query->whereHas('teacher_categories', function ($q) use ($categoryId) {
                    $q->where('teacher_categories.id', $categoryId);
                });
            }

            // Apply distance filtering and ordering
            $query->distance($latitude, $longitude, $radius, $unit)
                ->orderBy('distance');

            $teachers = $query->get();
            $student = auth()->user()->student;
            $liveRequest = null;

            // Create a single live request if teachers are found
            if ($teachers->isNotEmpty() && $student) {
                $endTime = (clone $currentTime)->modify("+{$duration} minutes");
                $liveRequest = LiveRequest::create([
                    'student_id' => $student->id,
                    'category_id' => $categoryId,
                    'start_time' => $currentTime,
                    'end_time' => $endTime,
                    'status' => 'pending'
                ]);

                // Attach teachers to the live request
                $liveRequest->teachers()->attach($teachers->pluck('id')->toArray());

                //send notification to the first teacher
                $firstTeacher = $teachers->first();
                $title = 'New Live Session Booking';
                $deepLink = route('teacher.appointment_log.live', ['id' => $liveRequest->id]);

                NotificationService::sendNotification(
                    $firstTeacher->id,
                    'teacher',
                    $title,
                    'A student has requested a live session with you',
                    $deepLink,
                    ['live_request_id' => $liveRequest->id]
                );

                SocketService::emit('live_request_prompt', [
                    'teacher_id' => $firstTeacher->id,
                    'live_request_id' => $liveRequest->id,
                    'deep_link' => $deepLink,
                    'title' => $title,
                    'body' => 'A student has requested a live session with you',
                ]);

                SocketService::emit('live_request_created', $liveRequest);
            }

            return response()->json([
                'status' => true,
                'message' => 'Teachers retrieved successfully',
                'data' => TeachersResource::collection($teachers),
                'live_request_id' => $liveRequest ? $liveRequest->id : null
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve teachers',
                'error' => $e->getMessage()
            ]);
        }
    }
}
