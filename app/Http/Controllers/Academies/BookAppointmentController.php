<?php

namespace App\Http\Controllers\Academies;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;

use App\Http\Resources\Web\BookAppointmentsResource;
use App\Models\AppointmentStatus;
use App\Models\BookAppointment;
use App\Models\Commission;
use Carbon\Carbon;

class BookAppointmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('academy');
    }
    public function getter($req = null, $export = null)
    {

        $academy = auth()->user()->academy;
        if ($req != null) {
            $academy_appointments =  $academy->appointments()->withAll();
            if ($req->trash && $req->trash == 'with') {
                $academy_appointments =  $academy_appointments->withTrashed();
            }
            if ($req->trash && $req->trash == 'only') {
                $academy_appointments =  $academy_appointments->onlyTrashed();
            }
            if ($req->column && $req->column != null && $req->search != null) {
                if (in_array($req->column, ['start_time', 'end_time'])) {
                    // Convert search time to 12-hour format to match DB format
                    $searchTime = date('h:i A', strtotime($req->search));
                    
                    if ($req->column === 'start_time') {
                        // For start_time, find appointments where start_time is >= search time
                        $academy_appointments = $academy_appointments->whereRaw("STR_TO_DATE(start_time, '%h:%i %p') >= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    } else {
                        // For end_time, find appointments where end_time is <= search time
                        $academy_appointments = $academy_appointments->whereRaw("STR_TO_DATE(end_time, '%h:%i %p') <= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    }
                } else {
                    // Original behavior for other columns
                    $academy_appointments = $academy_appointments->whereLike($req->column, $req->search);
                }
            } else if ($req->search && $req->search != null) {

                $academy_appointments = $academy_appointments->whereLike(['name', 'description'], $req->search);
            }

            if ($req->status_code) {
                $academy_appointments = $academy_appointments->where('appointment_status_code', $req->status_code);
            }

            if ($req->sort && $req->sort['field'] != null && $req->sort['type'] != null) {
                $academy_appointments = $academy_appointments->OrderBy($req->sort['field'], $req->sort['type']);
            } else {
                $academy_appointments = $academy_appointments->OrderBy('id', 'desc');
            }
            if ($export != null) { // for export do not paginate
                $academy_appointments = $academy_appointments->get();
                return $academy_appointments;
            }
            $totalAcademyAppointments = $academy_appointments->count();
            $academy_appointments = $academy_appointments->paginate($req->perPage);
            $academy_appointments = BookAppointmentsResource::collection($academy_appointments)->response()->getData(true);

            return $academy_appointments;
        }
        $academy_appointments = BookAppointmentsResource::collection($academy->appointments()->withAll()->orderBy('id', 'desc')->paginate(10))->response()->getData(true);
        return $academy_appointments;
    }
    public function getAcademiesFilteredAppointmentlogs(Request $request)
    {
        $appointments = $this->getter($request);
        $response = generateResponse($appointments, count($appointments['data']) > 0 ? true : false, 'Filter Appointment Logs Successfully', null, 'collection');
        return response()->json($response, 200);
    }
    public function showAcademiesAppointmentLogDetailPage($id)
    {
        $user = Auth()->user();
        $academy_id = $user->academy->id;
        $appointment = BookAppointment::withAll()->where('id', $id)->where('academy_id', $academy_id)->first();
        $appointment = new BookAppointmentsResource($appointment);
        $data = [
            'appointment' => $appointment,
        ];
        return Inertia::render('AppointmentLogDetail', $data);
    }
    public function updateAppointmentStatus(Request $request)
    {

        $user = Auth()->user();
        $settings = generalSettings();
        $academy_id = $user->academy->id;
        $appointment = BookAppointment::withAll()->where('id', $request->appointment_id)->where('academy_id', $academy_id)->first();
        
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
                $title = 'You have a new notification';
                if ($request->status_code == AppointmentStatus::$Accepted) {
                    $body = 'Your Appointment has been Accepted';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Rejected) {
                    $body = 'Your Appointment has been Rejected';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Cancel) {
                    $body = 'Your Appointment has been Canceled';
                    $deep_link = env('APP_URL') . '/appointment_log';
                }
                if ($request->status_code == AppointmentStatus::$Completed) {

                    $body = 'Your Appointment has been Completed';
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
        $academy_id = $user->academy->id;
        $appointment = BookAppointment::withAll()->where('id', $request->appointment_id)->where('academy_id', $academy_id)->first();
        if ($appointment) {
            $updated =  $appointment->update([
                'started_at' => Carbon::now(),
            ]);

            $response = generateResponse(null, true, 'Appointment Joined Successfully', null, 'object');
            return response()->json($response, 200);
        }
    }
}
