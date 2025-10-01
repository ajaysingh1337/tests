<?php

namespace App\Http\Controllers\API\Teachers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Teachers\Appointments\AddScheduleRequest;
use App\Http\Requests\API\Teachers\Appointments\CreateRequest;
use App\Http\Requests\API\Teachers\Appointments\DeleteRequest;
use App\Http\Resources\API\AppointmentSchedulesResource;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleSlot;
use App\Models\BookAppointment;
use App\Http\Resources\API\BookAppointmentsResource;
use App\Models\AppointmentStatus;
use App\Models\Commission;
use App\Models\LiveRequest;
use App\Services\NotificationService;
use App\Services\SocketService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class APITeacherAppointmentScheduleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['api', 'auth:api', 'verified', 'api_setting']);
        $this->middleware('teacher.api');
    }
    public function getter($req = null, $export = null)
    {
        $teacher = auth()->user()->teacher;
        if ($req != null) {
            $teacher_appointments =  $teacher->appointments()->withAll();
            if ($req->trash && $req->trash == 'with') {
                $teacher_appointments =  $teacher_appointments->withTrashed();
            }
            if ($req->trash && $req->trash == 'only') {
                $teacher_appointments =  $teacher_appointments->onlyTrashed();
            }
            if ($req->column && $req->column != null && $req->search != null) {
                $teacher_appointments = $teacher_appointments->whereLike($req->column, $req->search);
            } else if ($req->search && $req->search != null) {

                $teacher_appointments = $teacher_appointments->whereLike(['name', 'description'], $req->search);
            }

            if ($req->status_code) {
                $teacher_appointments = $teacher_appointments->where('appointment_status_code', $req->status_code);
            }

            if ($req->sort && $req->sort['field'] != null && $req->sort['type'] != null) {
                $teacher_appointments = $teacher_appointments->OrderBy($req->sort['field'], $req->sort['type']);
            } else {
                $teacher_appointments = $teacher_appointments->OrderBy('id', 'desc');
            }
            if ($export != null) { // for export do not paginate
                $teacher_appointments = $teacher_appointments->get();
                return $teacher_appointments;
            }
            $totalteacherAppointments = $teacher_appointments->count();
            $teacher_appointments = $teacher_appointments->paginate($req->perPage);
            $teacher_appointments = BookAppointmentsResource::collection($teacher_appointments)->response()->getData(true);

            return $teacher_appointments;
        }
        $teacher_appointments = BookAppointmentsResource::collection($teacher->appointments()->withAll()->orderBy('id', 'desc')->paginate(10))->response()->getData(true);
        return $teacher_appointments;
    }

    public function saveAppointmentSchedule(CreateRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->all();
            $user = Auth()->user();
            $teacher_id = $user->teacher->id;
            $data['teacher_id'] = $teacher_id;
            if ($request->is_schedule_required) {
                $records = AppointmentSchedule::where('teacher_id', $teacher_id)->where('appointment_type_id', $request->appointment_type_id)->get();
                $schedule_ids = $records->pluck('id')->toArray();
                if ($records) {
                    AppointmentScheduleSlot::whereIn('schedule_id', $schedule_ids)->delete();
                    $records->each->delete();
                }

                foreach ($request->selected_days as $key => $day) {
                    $schedule =  AppointmentSchedule::create([
                        'teacher_id' => $teacher_id,
                        'appointment_type_id' => $request->appointment_type_id,
                        'fee' => $request->fee,
                        'day' => $day,
                        'is_holiday' => isset($request->generated_slots[$day]) ? count($request->generated_slots[$day]) > 0 ? 1 : 0 : 1,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'slot_duration' => $request->interval,
                    ]);
                    if (isset($request->generated_slots[$day])) {
                        foreach ($request->generated_slots[$day] as $key => $slot) {
                            AppointmentScheduleSlot::create(
                                [
                                    'schedule_id' => $schedule->id,
                                    'start_time' => $slot['start_time'],
                                    'end_time' => $slot['end_time'],
                                    'is_active' => $slot['is_active'],
                                ]
                            );
                        }
                    }
                }
                $response = generateResponse(null, true, 'Schedules have been added Successfully', null, 'collection');
            } else {
                AppointmentSchedule::where('teacher_id', $teacher_id)->where('appointment_type_id', $request->appointment_type_id)->delete();
                if ($request->fee) {
                    $schedule = AppointmentSchedule::create($data);
                    $response = generateResponse(null, true, 'Schedules have been added Successfully', null, 'collection');
                } else {
                    $response = generateResponse(null, true, 'Schedules have been added Successfully', null, 'collection');
                }
            }
            DB::commit();
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = generateResponse(null, false, $e->getLine() . $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }
    public function getAppointmentSchedules(Request $request)
    {
        try {
            $request->validate([
                'appointment_type_id' => 'required|integer',
                'is_schedule_required' => 'required|integer',
            ]);
            $user = Auth()->user();
            if ($request->is_schedule_required) {
                $schedules = AppointmentSchedule::withAll()->where('teacher_id', $user->teacher->id)->where('appointment_type_id', $request->appointment_type_id)->get();
                $schedules = AppointmentSchedulesResource::collection($schedules)->keyBy('day');
                $response = generateResponse($schedules, true, "Appointment Schedules Fetched Successfully", null, 'collection');
            } else {
                $schedule = AppointmentSchedule::withAll()->where('teacher_id', $user->teacher->id)->where('appointment_type_id', $request->appointment_type_id)->first();
                if ($schedule) {
                    $schedule = new AppointmentSchedulesResource($schedule);
                } else {
                    $schedule = null;
                }
                $response = generateResponse($schedule, true, "Appointment Schedule Fetched Successfully", null, 'collection');
            }
            if ($response['data']->isEmpty()) {
                $response['data'] = null;
            }
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }
    public function deleteAppointmentScheduleSlots(DeleteRequest $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth()->user();
            $schedule = AppointmentSchedule::withAll()->where('teacher_id', $user->teacher->id)->where('appointment_type_id', $request->appointment_type_id)->where('day', $request->day)->first();
            $schedule->schedule_slots()->delete();
            $schedule->delete();
            $response = generateResponse(null, true, 'Schedule slots have been deleted Successfully', null, 'collection');
            DB::commit();
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }
    public function addNewAppointmentSchedule(AddScheduleRequest $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth()->user();
            $teacher_id = $user->teacher->id;
            $schedule = AppointmentSchedule::withAll()->where('teacher_id', $teacher_id)->where('appointment_type_id', $request->appointment_type_id)->first();
            $created = AppointmentSchedule::create([
                'teacher_id' => $teacher_id,
                'appointment_type_id' => $request->appointment_type_id,
                'fee' => $schedule->fee,
                'day' => $request->day,
                'is_holiday' => count($request->generated_slots) > 0 ? 1 : 0,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'slot_duration' => $request->interval,
            ]);
            foreach ($request->generated_slots as $key => $slot) {
                AppointmentScheduleSlot::create(
                    [
                        'schedule_id' => $created->id,
                        'start_time' => $slot['start_time'],
                        'end_time' => $slot['end_time'],
                        'is_active' => $slot['is_active'],
                    ]
                );
            }
            $response = generateResponse($created, true, 'Schedules have been added Successfully', null, 'collection');
            DB::commit();
            return response()->json($response, 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }
    public function getFilteredAppointmentlogs(Request $request)
    {
        $appointments = $this->getter($request);
        $response = generateResponse($appointments, count($appointments['data']) > 0 ? true : false, 'Filter Appointment Logs Successfully', null, 'collection');
        return response()->json($response, 200);
    }
    public function showAppointmentLogDetail(BookAppointment $book_appointment)
    {
        $user = Auth()->user();
        return ($book_appointment->teacher_id == $user->teacher->id)
            ? response()->json(generateResponse(new BookAppointmentsResource($book_appointment), true, 'Appointment Fetched Successfully', null, 'collection'), 200)
            : response()->json(generateResponse(null, false, 'Appointment Not Found', null, 'collection'), 404);
    }

    public function updateAppointmentStatus(Request $request, BookAppointment $book_appointment)
    {
        $request->validate([
            'appointment_status_code' => 'required|in:1,2,3,4,5',
        ]);
        $user = Auth()->user();
        $settings = generalSettings();
        if (($book_appointment->teacher_id == $user->teacher->id)) {
            $updated = $book_appointment->update([
                'appointment_status_code' => $request->appointment_status_code
            ]);
            if ($updated) {
                if ($request->appointment_status_code == AppointmentStatus::$Accepted) {
                    $title = 'Your Appointment has been Accepted';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->appointment_status_code == AppointmentStatus::$Rejected) {
                    $title = 'Your Appointment has been Rejected';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->appointment_status_code == AppointmentStatus::$Cancel) {

                    $title = 'Your Appointment has been Canceled';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->appointment_status_code == AppointmentStatus::$Completed) {

                    $title = 'Your Appointment has been Completed';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                    if ((int)$settings['enable_wallet_system']) {
                        if ($settings['commission_type'] == 'commission_base') {
                            $commission = Commission::where('appointment_type_id', $book_appointment->appointment_type_id)->first();
                            if ($commission && $commission->commission_type == 'fixed_rate') {
                                $commission_amount = $commission->rate ?? 0;
                                $final_amount = $book_appointment->fee - $commission_amount;
                            } else {
                                $rate = $commission->rate ?? 0;
                                $percentage_value = ($book_appointment->fee / 100) * $rate;
                                $commission_amount = $percentage_value;
                                $final_amount = $book_appointment->fee - $percentage_value;
                            }
                        } else {
                            $final_amount = $book_appointment->fee;
                        }
                        $meta = ['details' => 'Deposit on Completion of Appointment # ' . $book_appointment->id];

                        $user->deposit($final_amount, $meta);
                    }
                }
                \App\Services\NotificationService::sendNotification($book_appointment->student, 'student', $title, $body, $deep_link, ['appointment_id' => $book_appointment->id]);
            }
        }
        $book_appointment = new BookAppointmentsResource($book_appointment);
        $response = generateResponse($book_appointment, isset($book_appointment) ? true : false, 'Appointment Status Updated Successfully', null, 'collection');
        return response()->json($response, 200);
    }
    public function getAppointmentCommission(Request $request)
    {
        $request->validate([
            'appointment_type_id' => 'required|integer',
        ]);
        $user = Auth()->user();

        $commission = Commission::where('appointment_type_id', $request->appointment_type_id)->first();
        $response = generateResponse($commission, true, "Commission Fetched Successfully", null, 'collection');
        return response()->json($response);
    }
    /**
     * Return the live appointment log detail for teachers (API).
     * Mirrors logic from web controller but returns JSON instead of Inertia view.
     *
     * @param int $id The ID of the live request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showLiveTeacherAppointmentLogDetail($id)
    {
        try {
            // Ensure the authenticated user has a teacher profile
            $user = Auth()->user();
            if (!$user || !$user->teacher) {
                return response()->json(generateResponse(null, false, 'Authentication required', null, 'collection'), 401);
            }

            $teacher = $user->teacher;

            // Validate the ID parameter
            if (!is_numeric($id) || (int)$id <= 0) {
                return response()->json(generateResponse(null, false, 'Invalid appointment ID', null, 'collection'), 422);
            }

            // Fetch the live request ensuring it is associated with this teacher
            $liveRequest = LiveRequest::with(['student', 'category', 'teachers'])
                ->whereHas('teachers', function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                })
                ->where('id', (int)$id)
                ->first();

            if (!$liveRequest) {
                return response()->json(generateResponse(null, false, 'Appointment not found or you do not have permission to view it', null, 'collection'), 404);
            }

            return response()->json(generateResponse($liveRequest, true, 'Appointment Fetched Successfully', null, 'collection'), 200);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in showLiveTeacherAppointmentLogDetail: ' . $e->getMessage());
            return response()->json(generateResponse(null, false, 'A database error occurred. Please try again later.', null, 'collection'), 500);
        } catch (\Exception $e) {
            Log::error('Error in showLiveTeacherAppointmentLogDetail: ' . $e->getMessage());
            return response()->json(generateResponse(null, false, 'An unexpected error occurred. Please try again later.', null, 'collection'), 500);
        }
    }

    public function acceptLiveRequest(Request $request)
    {
        $request->validate([
            'live_request_id' => 'required|exists:live_requests,id',
        ]);

        $liveRequestId = $request->live_request_id;

        // Get teacher ID from auth or request
        $teacherId = auth()->user()->teacher ? auth()->user()->teacher->id : $request->teacher_id;

        if(!$teacherId){
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ]);
        }

        // Find the live request
        $liveRequest = LiveRequest::findOrFail($liveRequestId);

        // Verify the teacher is associated with this request
        if (!$liveRequest->teachers()->where('teacher_id', $teacherId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Live request expired.'
            ], 400);
        }

        // Update live request status to 'accepted'
        $liveRequest->update(['status' => 'accepted']);

        //remove all teachers from this live request
        $liveRequest->teachers()->detach();

        $liveRequest["teacher_id"] = $teacherId;

        SocketService::emit('live_request_accepted', $liveRequest);

        return response()->json([
            'success' => true,
            'message' => 'Live request has been accepted.',
        ], 200);
    }

    /**
     * Handle teacher rejection for a live request
     * Can be called directly by teacher or automatically when they don't respond
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rejectLiveRequest(Request $request)
    {
        $request->validate([
            'live_request_id' => 'required|exists:live_requests,id',
        ]);
        
        $liveRequestId = $request->live_request_id;

        $teacher_id = auth()->user()->teacher ? auth()->user()->teacher->id : $request->teacher_id;
        
        if (!$teacher_id) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ]);
        }

        // Find the live request with teachers relationship loaded
        $liveRequest = LiveRequest::with('teachers')->findOrFail($liveRequestId);

        // Delete the teacher's association from the pivot table
        DB::table('live_request_teachers')
            ->where('request_id', $liveRequestId)
            ->where('teacher_id', $teacher_id)
            ->delete();

        // IMPORTANT: Reload teachers after removal to avoid using stale relationship
        $liveRequest->unsetRelation('teachers');
        $liveRequest = LiveRequest::with('teachers')->findOrFail($liveRequestId);

        // Get the next teacher if any (will not include the rejecting teacher now)
        $nextTeacher = $liveRequest->teachers->first();
        
        // If there's a next teacher, send them a notification
        if ($nextTeacher) {
            $title = 'New Live Session Booking';
            $deepLink = route('teacher.appointment_log.live', ['id' => $liveRequest->id]);

            NotificationService::sendNotification(
                $nextTeacher->id,
                'teacher',
                $title,
                'A student has requested a live session with you',
                $deepLink,
                ['live_request_id' => $liveRequest->id]
            );

            SocketService::emit('live_request_prompt', [
                'teacher_id' => $nextTeacher->id,
                'live_request_id' => $liveRequest->id,
                'deep_link' => $deepLink,
                'title' => $title,
                'body' => 'A student has requested a live session with you',
            ]);
        }

        SocketService::emit('live_request_rejected', $liveRequest);
        
        return response()->json([
            'success' => true,
            'message' => 'Teacher has been removed from this live request.'
        ]);
    }
}
