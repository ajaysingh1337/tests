<?php

namespace App\Http\Controllers\Teachers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Http\Resources\Web\AppointmentSchedulesResource;
use App\Http\Resources\Web\AppointmentTypesResource;
use App\Http\Resources\Web\TeachersResource;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentType;
use App\Models\BookAppointment;
use App\Models\BookedService;
use App\Models\Gateway;
use App\Models\Teacher;
use App\Models\TeacherLiveAvailability;
use App\Http\Requests\Teachers\UpdateLiveAvailabilityRequest;
use App\Models\LiveRequest;
use App\Services\NotificationService;
use App\Services\SocketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TeacherProfileController extends Controller
{
    public function __construct() {}

    public function myProfile(Request $request)
    {
        $user = auth()->user();
        $teacher = $user->teacher;
        $teacher = Teacher::withChildrens()->active()->withAll()->where('id', $teacher->id)->first();
        if (!$teacher) {
            abort(404);
        }
        $teacher = new TeachersResource($teacher);
        $appointment_types = AppointmentType::active()->get();
        $appointment_types = AppointmentTypesResource::collection($appointment_types);
        return Inertia::render('Teachers/Profile', [
            'teacher' => $teacher,
            'appointment_types' => $appointment_types
        ]);
    }
    public function profile(Request $request)
    {
        $teacher = Teacher::withChildrens()->active()->approved()->withAll()->where('user_name', $request->user_name)->first();
        if (!$teacher) {
            abort(404);
        }
        $teacher = new TeachersResource($teacher);
        $appointment_types = AppointmentType::active()->get();
        $appointment_types = AppointmentTypesResource::collection($appointment_types);
        $hasAppointments = false;
        $hasServices = false;
        if(auth()->user()->student){
            $hasAppointments = BookAppointment::where('teacher_id', $teacher->id)->where('student_id', auth()->user()->student->id)->where('appointment_status_code', 5)->exists();
            $hasServices = BookedService::where('teacher_id', $teacher->id)->where('student_id', auth()->user()->student->id)->where('service_status_code', 5)->exists();
        } else {
            $hasAppointments = false;
            $hasServices = false;
        }
        return Inertia::render('Teachers/Profile', [
            'teacher' => $teacher,
            'appointment_types' => $appointment_types,
            'can_review' => $hasAppointments || $hasServices,
        ]);
    }

    public function reviews(Request $request)
    {
        $teacher = Teacher::withChildrens()->active()->approved()->withAll()->where('user_name', $request->user_name)->first();
        if (!$teacher) {
            abort(404);
        }
        $teacher = new TeachersResource($teacher);
        return Inertia::render('Teachers/Reviews', [
            'teacher' => $teacher
        ]);
    }

    public function bookAppointment(Request $request, $user_name)
    {
        $teacher = Teacher::where('user_name', $user_name)->first();
        $teacher_id = $teacher->id;
        $appointment_type = AppointmentType::select('id', 'is_schedule_required')->where('type', $request->type)->first();
        $appointment_type_id = $appointment_type->id;
        $day = strtolower(Date('l'));
        $date = today();
        if ($appointment_type->is_schedule_required) {
            $schedule = AppointmentSchedule::with('appointment_type')->with('schedule_slots')->where('teacher_id', $teacher_id)->where('appointment_type_id', $appointment_type_id)->where('day', $day)->first();
        } else {
            $schedule = AppointmentSchedule::with('appointment_type')->with('schedule_slots')->where('teacher_id', $teacher_id)->where('appointment_type_id', $appointment_type_id)->first();
        }
        if ($schedule) {
            $scheduleSlots = $schedule->schedule_slots;
            if (count($scheduleSlots) > 0) {
                foreach ($scheduleSlots as $scheduleSlot) {
                    $is_disabled = BookAppointment::where('teacher_id', $teacher_id)
                        ->whereDate('date', $date)
                        ->where('is_paid', 1)
                        ->where(function ($q) use ($scheduleSlot) {
                            $q->where(function ($z) use ($scheduleSlot) {
                                $z->where('start_time', $scheduleSlot->start_time);
                                $z->where('end_time', $scheduleSlot->end_time);
                            });
                        })->count();

                    $now = Carbon::now();

                    // Extract only the date part from $date
                    $onlyDate = Carbon::parse($date)->format('Y-m-d');

                    // Combine date and start_time correctly
                    $slotDateTime = Carbon::parse($onlyDate . ' ' . $scheduleSlot->start_time);

                    // Disable if it's today and the time is in the past
                    if (Carbon::parse($onlyDate)->isToday() && $slotDateTime->lt($now)) {
                        $is_disabled = 1;
                    }

                    $scheduleSlot['is_disabled'] = $is_disabled;
                }
            }
            $schedule = new AppointmentSchedulesResource($schedule);
        } else {
            $schedule = null;
        }
        $gateways = Gateway::where('status', 1)->orderBy('sort_by', 'ASC')->get();

        //dd($schedule);

        return Inertia::render('Teachers/BookAppointment', [
            'schedule' => $schedule,
            'teacher_id' => $teacher_id,
            'teacher' => $teacher,
            'appointment_type_name' => $appointment_type->display_name,
            'appointment_type_id' => $appointment_type_id,
            'is_schedule_required' => $appointment_type->is_schedule_required,
            "gateways" => $gateways
        ]);
    }

    public function updateLiveAvailability(UpdateLiveAvailabilityRequest $request)
    {
        try {
            $teacher = auth()->user()->teacher;
            if($request->latitude && $request->longitude){
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
            return back()->with('alert', [
                'type' => 'success',
                'message' => 'Live Availability Updated Successfully'
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return back()->with('alert', [
                'type' => 'error',
                'message' => 'Live Availability Update Failed'
            ]);
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

        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $radius = $request->input('radius', 10);
        $unit = $request->input('unit', 'km');
        $categoryId = $request->input('category');
        $currentTime = Carbon::parse($request->input('current_time'));
        $duration = (int) $request->input('duration', 30); // Default to 30 minutes if not provided

        // Build the base query
        $query = Teacher::with(['user', 'live_availability', 'teacher_categories'])
            ->whereHas('live_availability', function($q) use ($currentTime, $duration) {
                // Filter out offline and in-call statuses
                $q->where('status', 'online')
                  // Check if current time is within the availability window
                  ->where('start_time', '<=', $currentTime)
                  // Check if the requested duration fits within the availability window
                  ->where('end_time', '>=', (clone $currentTime)->modify("+{$duration} minutes"));
            });

        // Filter by category if provided
        if ($categoryId) {
            $query->whereHas('teacher_categories', function($q) use ($categoryId) {
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

            // Emit a targeted socket event to the first teacher to ensure prompt delivery
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
    }
}
