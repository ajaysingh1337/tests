<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\BookAppointment;
use App\Models\LiveRequest;
use App\Models\AppointmentType;
use App\Models\TeacherLiveAvailability;
use App\Http\Resources\Web\BookAppointmentsResource;
use App\Models\AppointmentStatus;
use App\Models\Fund;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\NotificationService;
use App\Services\SocketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Response;
use Inertia\ResponseFactory;

class BookAppointmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('teacher');
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
                if (in_array($req->column, ['start_time', 'end_time'])) {
                    // Convert search time to 12-hour format to match DB format
                    $searchTime = date('h:i A', strtotime($req->search));
                    
                    if ($req->column === 'start_time') {
                        // For start_time, find appointments where start_time is >= search time
                        $teacher_appointments = $teacher_appointments->whereRaw("STR_TO_DATE(start_time, '%h:%i %p') >= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    } else {
                        // For end_time, find appointments where end_time is <= search time
                        $teacher_appointments = $teacher_appointments->whereRaw("STR_TO_DATE(end_time, '%h:%i %p') <= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    }
                } else {
                    // Original behavior for other columns
                    $teacher_appointments = $teacher_appointments->whereLike($req->column, $req->search);
                }
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
    public function getteacherFilteredAppointmentlogs(Request $request)
    {
        $appointments = $this->getter($request);
        $response = generateResponse($appointments, count($appointments['data']) > 0 ? true : false, 'Filter Appointment Logs Successfully', null, 'collection');
        return response()->json($response, 200);
    }
    public function showteacherAppointmentLogDetailPage($id)
    {
        $user = Auth()->user();
        $teacher_id = $user->teacher->id;
        $appointment = BookAppointment::withAll()->where('id', $id)->where('teacher_id', $teacher_id)->first();
        $appointment = new BookAppointmentsResource($appointment);
        $data = [
            'appointment' => $appointment,
        ];
        return Inertia::render('AppointmentLogDetail', $data);
    }
    /**
     * Display the live appointment log detail page for teachers.
     *
     * @param int $id The ID of the live request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function showLiveTeacherAppointmentLogDetailPage($id)
    {
        try {
            // Check if user is authenticated and has a teacher profile
            if (!Auth()->check() || !Auth()->user()->teacher) {
                return redirect()->route('login')->with('error', 'Authentication required');
            }

            $teacher = Auth()->user()->teacher;

            // Validate the ID parameter
            if (!is_numeric($id) || $id <= 0) {
                return back()->with('error', 'Invalid appointment ID');
            }

            // Get the live request with eager loading
            $liveRequest = LiveRequest::with(['student', 'category', 'teachers'])
                ->whereHas('teachers', function ($query) use ($teacher) {
                    $query->where('teacher_id', $teacher->id);
                })
                ->where('id', $id)
                ->first();

            // Check if the live request exists and is accessible
            if (!$liveRequest) {
                return back()->with('error', 'Appointment not found or you do not have permission to view it');
            }

            $data = [
                'appointment' => $liveRequest,
            ];

            return Inertia::render('LiveAppointmentLogDetail', $data);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in showLiveTeacherAppointmentLogDetailPage: ' . $e->getMessage());
            return back()->with('error', 'A database error occurred. Please try again later.');
        } catch (\Exception $e) {
            Log::error('Error in showLiveTeacherAppointmentLogDetailPage: ' . $e->getMessage());
            return back()->with('error', 'An unexpected error occurred. Please try again later.');
        }
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
        $liveRequestId = auth()->user()->student ? null : $request->live_request_id;

        if(!$liveRequestId){
            $student_id = auth()->user()->student->id;
            $liveRequestId = LiveRequest::where('student_id', $student_id)
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail()
            ->id;
        }

        // Get teacher from authenticated user
        $isStudent = auth()->user()->student !== null;
        $teacher_id = auth()->user()->teacher ? auth()->user()->teacher->id : $request->teacher_id;
        
        if (!$teacher_id) {
            if ($isStudent) {
                return back()->with([
                    'flash' => [
                        'type' => 'error',
                        'message' => 'Teacher not found'
                    ]
                ]);
            }
            return back()->with('error', 'Teacher not found');
        }

        // Find the live request (initially) with teachers relationship loaded
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

            // Emit a targeted socket event to the next teacher to ensure prompt delivery
            SocketService::emit('live_request_prompt', [
                'teacher_id' => $nextTeacher->id,
                'live_request_id' => $liveRequest->id,
                'deep_link' => $deepLink,
                'title' => $title,
                'body' => 'A student has requested a live session with you',
            ]);
        }

        SocketService::emit('live_request_rejected', $liveRequest);

        // If this is an AJAX/JSON request, return JSON to avoid browser redirect chains
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Teacher has been removed from this live request.'
            ], 200);
        }

        if ($isStudent) {
            return back()->with([
                'flash' => [
                    'type' => 'success',
                    'message' => 'Teacher has been removed from this live request.'
                ]
            ]);
        }
        
        return back()->with([
            'flash' => [
                'type' => 'success',
                'message' => 'Teacher has been removed from this live request.'
            ]
        ]);
    }
    public function acceptLiveRequest(Request $request)
    {
        $request->validate([
            'live_request_id' => 'required|exists:live_requests,id',
        ]);

        $liveRequestId = $request->live_request_id;

        // Get teacher ID from auth or request
        $teacherId = auth()->user()->teacher->id ?? $request->teacher_id;

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
    public function updateAppointmentStatus(Request $request)
    {
        $settings = generalSettings();
        $user = Auth()->user();
        $teacher_id = $user->teacher->id;
        $appointment = BookAppointment::withAll()->where('id', $request->appointment_id)->where('teacher_id', $teacher_id)->first();

        if ($appointment) {
            $updated =  $appointment->update([
                'appointment_status_code' => $request->status_code
            ]);
            if ($request->status_code == AppointmentStatus::$Completed) {
                $appointment->update([
                    'ended_at' => Carbon::now(),
                ]);
            }
            if ($updated) {
                if ($request->status_code == AppointmentStatus::$Accepted) {
                    $title = 'Your Appointment has been Accepted';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Rejected) {
                    $title = 'Your Appointment has been Rejected';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Cancel) {

                    $title = 'Your Appointment has been Canceled';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Completed) {

                    $title = 'Your Appointment has been Completed';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/appointment_log';



                    if ((int)$settings['enable_wallet_system']) {

                        if ($settings['commission_type'] == 'commission_base') {
                            $commission = Commission::where('appointment_type_id', $appointment->appointment_type_id)->first();
                            if ($commission && $commission->commission_type == 'fixed_rate') {
                                $commission_amount = $commission->rate ?? 0;
                                $final_amount = $appointment->fee - $commission_amount;
                            } else {
                                $rate = $commission->rate ?? 0;
                                $percentage_value = ($appointment->fee / 100) * $rate;
                                $commission_amount = $percentage_value;
                                $final_amount = $appointment->fee - $percentage_value;
                            }
                        } else {
                            $final_amount = $appointment->fee;
                        }
                        $meta = ['details' => 'Deposit on Completion of Appointment # ' . $appointment->id];

                        $user->deposit($final_amount, $meta);
                    }
                }
                \App\Services\NotificationService::sendNotification($appointment->student, 'student', $title, $body, $deep_link, ['appointment_id' => $appointment->id]);
            }

            if ($request->status_code == 2) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Appointment Accepted Successfully',
                ]);
            } elseif ($request->status_code == 3) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Appointment Rejected Successfully',
                ]);
            } elseif ($request->status_code == 5) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Appointment Mark as Completed Successfully',
                ]);
            }
            return redirect()->back();
        }
    }
    public function updateAppointmentStarted(Request $request)
    {
        $user = Auth()->user();
        $teacher_id = $user->teacher->id;
        $appointment = BookAppointment::withAll()->where('id', $request->appointment_id)->where('teacher_id', $teacher_id)->first();
        if ($appointment) {
            $updated =  $appointment->update([
                'started_at' => Carbon::now(),
            ]);

            $response = generateResponse(null, true, 'Appointment Joined Successfully', null, 'object');
            \App\Services\NotificationService::sendNotification($appointment->student, 'student', 'Appointment Joined', 'Your appointment has started', env('APP_URL') . '/appointment_log', ['appointment_id' => $appointment->id]);
            return response()->json($response, 200);
        }
    }

    public function updateLiveAppointmentStatus(Request $request)
    {
        $user = Auth()->user();
        $teacher_id = $user->teacher->id;
        $liveRequest = LiveRequest::withAll()->where('id', $request->appointment_id)->where('teacher_id', $teacher_id)->first();
        if ($liveRequest) {
            $updated =  $liveRequest->update([
                'status' => $request->status,
            ]);
            $response = generateResponse(null, true, 'Appointment Status Updated Successfully', null, 'object');
            return response()->json($response, 200);
        }
    }
}
