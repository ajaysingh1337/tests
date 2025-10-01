<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentMethods\StripeController;
use App\Http\Requests\API\Students\BookAppointmentRequest;
use App\Http\Resources\API\BookAppointmentsResource;
use App\Models\AppointmentSchedule;
use App\Models\AppointmentScheduleSlot;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\BookAppointment;
use App\Models\LiveRequest;
use App\Models\TeacherLiveAvailability;
use App\Services\NotificationService;
use App\Services\SocketService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class APIAppointmentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('api');
        $this->middleware('verified');
        $this->middleware('api_setting');
        $this->middleware('student.api');
    }
    public function getter($req = null, $export = null)
    {

        $student = auth()->user()->student;
        if ($req != null) {
            $student_appointments =  $student->appointments()->withAll();
            if ($req->trash && $req->trash == 'with') {
                $student_appointments =  $student_appointments->withTrashed();
            }
            if ($req->trash && $req->trash == 'only') {
                $student_appointments =  $student_appointments->onlyTrashed();
            }
            if ($req->column && $req->column != null && $req->search != null) {
                $student_appointments = $student_appointments->whereLike($req->column, $req->search);
            } else if ($req->search && $req->search != null) {

                $student_appointments = $student_appointments->whereLike(['name', 'description'], $req->search);
            }
            if ($req->status_code) {
                $student_appointments = $student_appointments->where('appointment_status_code', $req->status_code);
            }

            if ($req->sort && $req->sort['field'] != null && $req->sort['type'] != null) {
                $student_appointments = $student_appointments->OrderBy($req->sort['field'], $req->sort['type']);
            } else {
                $student_appointments = $student_appointments->OrderBy('id', 'desc');
            }
            if ($export != null) { // for export do not paginate
                $student_appointments = $student_appointments->get();
                return $student_appointments;
            }
            $totalStudentAppointments = $student_appointments->count();
            $student_appointments = $student_appointments->paginate($req->perPage);
            $student_appointments = BookAppointmentsResource::collection($student_appointments)->response()->getData(true);

            return $student_appointments;
        }
        $student_appointments = BookAppointmentsResource::collection($student->appointments()->withAll()->orderBy('id', 'desc')->paginate(10))->response()->getData(true);
        return $student_appointments;
    }

    public function bookAppointment(BookAppointmentRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->all();
            $user = Auth()->user();
            $student = $user->student->id;
            $appointment_type = AppointmentType::where('id', $request->appointment_type_id)->first();
            if ($appointment_type->is_schedule_required) {
                $schedule_slot = AppointmentScheduleSlot::with('appointment_schedule')->where('schedule_id', $request->appointment_schedule_id)->where('id', $request->selected_schedule_id)->first();
                $data['start_time'] = $schedule_slot->start_time;
                $data['end_time'] = $schedule_slot->end_time;
                $data['fee'] = $schedule_slot->appointment_schedule->fee;
            } else {
                if (isset($request->teacher_id)) {
                    $appointment_schedule = AppointmentSchedule::where('teacher_id', $request->teacher_id)->where('appointment_type_id', $request->appointment_type_id)->first();
                } else {
                    $appointment_schedule = AppointmentSchedule::where('academy_id', $request->academy_id)->where('appointment_type_id', $request->appointment_type_id)->first();
                }
                $data['start_time'] = null;
                $data['end_time'] = null;
                $data['fee'] = $appointment_schedule->fee;
            }

            $data['student_id'] = $student;
            $data['appointment_status_code'] = AppointmentStatus::$Pending;
            if ($request->hasFile('attachment')) {
                $data['attachment_url'] = uploadFile($request, 'attachment', 'booked_appointments');
            }
            $request->merge(['amount' => $data['fee']]);

            $fund_request = PaymentController::addFundRequest($request);
            // dd($fund_request);
            $data['fund_id'] = $fund_request['fund']['id'] ?? null;
            if ($fund_request['fund'] ?? false) {
                $data['is_paid'] = 0;
                $appointment = BookAppointment::create($data);
                // $request->merge(['fee' => $data['fee']]);
                $appointment->fund_transaction = $fund_request['fund']->transaction ?? null;
                // $appointment->fund = $fund_request['fund'];
                $response = generateResponse($appointment, true, 'Appointment Booked Successfully', null, 'collection');
                //send notification
                $deep_link = env('APP_URL') . '/appointment_log';
                \App\Services\NotificationService::sendNotification($request->teacher_id, 'teacher', 'You have a new appointment booking', 'You have a new appointment booking', $deep_link, ['appointment_id' => $appointment->id]);
                DB::commit();
                return response()->json($response, 200);
            } else {
                $response = generateResponse($fund_request, false, 'Error', null, 'collection');
                return response()->json($response, 200);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $response = generateResponse(null, false, $e->getMessage(), null, 'collection');
            return response()->json($response, 200);
        }
    }
    /**
     * Book a live appointment from a live request
     * 
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function bookLiveAppointment(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
                'student_id' => 'nullable|exists:users,id',
                'appointment_type_id' => 'required|exists:appointment_types,id',
                'live_request_id' => 'required|exists:live_requests,id',
                'description' => 'nullable|string',
                'date' => 'required|date_format:Y-m-d\TH:i:s.u\Z',
            ]);

            // Get the live request with relationships
            $liveRequest = LiveRequest::with(['student', 'category'])
                ->findOrFail($data['live_request_id']);

            // Get the teacher's live availability to get the fee
            $liveAvailability = TeacherLiveAvailability::where('teacher_id', $data['teacher_id'])
                ->where('status', 'online')
                ->firstOrFail();
            
            // Prepare appointment data (derive student from auth; request student_id ignored unless needed in future)
            $data['student_id'] = auth()->user()->student->id;
            $data['start_time'] = Carbon::parse($liveRequest->start_time)->format('g:i A');
            $data['end_time'] = Carbon::parse($liveRequest->end_time)->format('g:i A');
            $data['fee'] = $liveAvailability->fee ?? 0;
            $data['appointment_status_code'] = AppointmentStatus::$Pending;
            $data['live_request_id'] = $liveRequest->id;
            
            // Format the date to Y-m-d (as expected by the database)
            $data['date'] = Carbon::parse($data['date'])->format('Y-m-d\TH:i:s.v\Z');
            
            // Log the data being used to create the appointment
            Log::info('Creating appointment with data:', $data);

            // Handle different payment gateways (scope fund creation appropriately)
            if ($request->gateway === 'wallet') {
                // Charge wallet first; no fund request
                $wallet = new WalletController();
                $walletResponse = $wallet->payThroughUserWallet($data['fee']);

                if ($walletResponse->getData()->status) {
                    $data['is_paid'] = 1;
                    $appointment = BookAppointment::create($data);

                    // Send notification to teacher
                    $title = 'New Live Session Booking';
                    $deepLink = route('teacher.appointment_log.detail', ['id' => $appointment->id]);
                    NotificationService::sendNotification(
                        $appointment->teacher_id,
                        'teacher',
                        $title,
                        'A student has booked a live session with you',
                        $deepLink,
                        ['appointment_id' => $appointment->id]
                    );
                    
                    SocketService::emit('live_payment_completed', $appointment);

                    $response = generateResponse($appointment, true, 'Appointment Booked Successfully', null, 'collection');
                    DB::commit();
                    return response()->json($response, 200);
                }

                throw new \Exception($walletResponse->getData()->msg ?? 'Payment failed');
            }

            if ($request->gateway === 'stripe') {
                // Create fund request for stripe
                $fundRequest = PaymentController::addFundRequest(new Request([
                    'amount' => $data['fee'],
                    'gateway' => 'stripe',
                    'description' => 'Live session - ' . ($liveRequest->category->name ?? 'Live Session')
                ]));

                if (!is_array($fundRequest) || !isset($fundRequest['fund'])) {
                    Log::error('Invalid payment request response', [
                        'response' => $fundRequest,
                        'user_id' => auth()->id(),
                        'amount' => $data['fee']
                    ]);
                    $response = generateResponse($fundRequest, false, 'Payment request failed', null, 'collection');
                    DB::rollBack();
                    return response()->json($response, 200);
                }

                $data['fund_id'] = $fundRequest['fund']->id ?? $fundRequest['fund']['id'];
                $appointment = BookAppointment::create($data);
                $appointment->fund_transaction = $fundRequest['fund']->transaction ?? null;
                $appointment->payment_url = $fundRequest['payment_url'] ?? null;

                SocketService::emit('live_payment_completed', $appointment);
                // Send notification
                $title = 'New Live Session Booking';
                $deepLink = route('teacher.appointment_log.detail', ['id' => $appointment->id]);
                NotificationService::sendNotification(
                    $appointment->teacher_id,
                    'teacher',
                    $title,
                    'A student has booked a live session with you',
                    $deepLink,
                    ['appointment_id' => $appointment->id]
                );

                $response = generateResponse($appointment, true, 'Appointment Booked Successfully', null, 'collection');
                DB::commit();
                return response()->json($response, 200);
            }

            // Default to bank transfer: create fund request and appointment
            $fundRequest = PaymentController::addFundRequest(new Request([
                'amount' => $data['fee'],
                'gateway' => 'bank-transfer',
                'description' => 'Live session - ' . ($liveRequest->category->name ?? 'Live Session')
            ]));

            if (!is_array($fundRequest) || !isset($fundRequest['fund'])) {
                Log::error('Invalid payment request response', [
                    'response' => $fundRequest,
                    'user_id' => auth()->id(),
                    'amount' => $data['fee']
                ]);
                $response = generateResponse($fundRequest, false, 'Payment request failed', null, 'collection');
                DB::rollBack();
                return response()->json($response, 200);
            }

            $data['fund_id'] = $fundRequest['fund']->id ?? $fundRequest['fund']['id'];
            $appointment = BookAppointment::create($data);
            $appointment->fund_transaction = $fundRequest['fund']->transaction ?? null;
            $appointment->payment_url = $fundRequest['payment_url'] ?? null;

            // Send notification
            $title = 'New Live Session Booking';
            $deepLink = route('teacher.appointment_log.detail', ['id' => $appointment->id]);
            NotificationService::sendNotification(
                $appointment->teacher_id,
                'teacher',
                $title,
                'A student has booked a live session with you',
                $deepLink,
                ['appointment_id' => $appointment->id]
            );

            $response = generateResponse($appointment, true, 'Appointment Booked Successfully', null, 'collection');
            DB::commit();
            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Error creating live appointment: ' . $e->getMessage());
            $response = generateResponse(null, false, 'Failed to create live appointment: ' . $e->getMessage(), null, 'collection');
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
        $appointment = BookAppointment::withAll()->find($book_appointment->id);
        return ($book_appointment->student_id == $user->student->id)
            ? response()->json(generateResponse(new BookAppointmentsResource($appointment), true, 'Appointment Fetched Successfully', null, 'collection'), 200)
            : response()->json(generateResponse(null, false, 'Appointment Not Found', null, 'collection'), 404);
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
