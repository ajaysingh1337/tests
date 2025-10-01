<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\Web\AppointmentStatusResource;
use App\Http\Resources\Web\AppointmentTypesResource;
use App\Models\AppointmentStatus;
use App\Models\AppointmentType;
use App\Models\User;
use App\Services\NotificationService;
use App\Http\Resources\Web\BookAppointmentsResource;
use App\Models\BookAppointment;
use Carbon\Carbon;

class CallController extends Controller
{
    public function __construct()
    {
       // $this->middleware('auth');
        $this->middleware('auth')->only(['index']);
    }
    public function end($id, Request $request)
    {
        if (!$id) {
            return response()->json(['error' => 'Missing id'], 404);
        }
        $userId=$request->input('user_id');
        $type=$request->input('type');
        $user = User::find($userId);
        if (!$user) {
              return response()->json(['error' => 'User not found'], 404);
        }
        $appointment = BookAppointment::withAll()->where('id', $id)->first();
        if($user->teacher && $appointment){            
            if($appointment->student){                
                $title='Call Ended.';
                $body='The call is ended now.';
                $deep_link =env('APP_URL') . '/appointment_log';
                \App\Services\NotificationService::sendNotification($appointment->student, 'student', $title, $body, $deep_link, ['appointment_id' => $appointment->id]);
            
            }
        }        
        return response()->json(['data' => 'Call ended'], 200);
    }
    public function checkExists($id)
    {
        if (!$id) {
            return response()->json(['error' => 'Missing external_id'], 400);
        }

        $baseUrl = env('CALL_SERVER_URL'); // Set in Laravel .env
        $url = "{$baseUrl}/room/check_exists?external_id={$id}";

        try {
            $response = Http::get($url);

            if ($response->successful()) {
                $data = $response->json('data');

                if (
                    isset($data['participants_ids']) &&
                    is_array($data['participants_ids']) &&
                    count($data['participants_ids']) > 0
                ) {
                    return response()->json(['data' => $data], 200);
                }
            }

            return response()->json(['data' => false], 200);
        } catch (\Exception $e) {
            \Log::error('Error checking call room: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to check call room'], 500);
        }
    }
    public function join($id, Request $request)
    {
       
        $request->validate([
            'user_id' => 'required|numeric',
            'type' => 'required|string',
        ]);
        $userId=$request->query('user_id');
        $type=$request->query('type');
        $user = User::find($userId);
        if (!$user) {
            abort(404, 'User not found');
        }
        $appointment = BookAppointment::withAll()->where('id', $id)->first();
        $start = Carbon::parse($appointment->date)->setTimeFromTimeString($appointment->start_time);
        $formattedStart = $start->format('Y-m-d H:i:s');
        $end = Carbon::parse($appointment->date)->setTimeFromTimeString($appointment->end_time);
        $durationInMinutes = $start->diffInMinutes($end); 
        $userObj=[];
        $userObj['id']=$user->id;
        $userObj['first_name']=$user->first_name;
        $userObj['last_name']=$user->last_name;
        $userObj['role']='user';
        $userObj['profile_image'] =asset('assets/images/dashboard-image-1.png');
        if($type== 'academy'){
             $userObj['role']='academy';
        }
        if($type == 'teacher'){
            $userObj['role']='teacher';
            $userObj['first_name']=$user->teacher->first_name;
            $userObj['last_name']=$user->teacher->last_name;
            $userObj['profile_image'] = $user->teacher->image ? asset($user->teacher->image) : asset('assets/images/dashboard-image-1.png'); 
            
        }
        if($type== 'student'){
            $userObj['role']='student';
            $userObj['first_name']=$user->student->first_name;
            $userObj['last_name']=$user->student->last_name;
            $userObj['profile_image'] = $user->student->image ? asset($user->student->image) : asset('assets/images/dashboard-image-1.png'); 
        }
        $callType='booking';
        $room_id='ot_'.$callType.'_'.$id;
        $app_data=[
            'id'=>$id,
            'meeting_id'=>$id,
            'external_id'=>$id,
            'type'=>'booking',
            'title'=>'Meet Now',
            'room_id'=>$room_id,            
            'start_time'=>$formattedStart,
            'duration_minutes'=>$durationInMinutes
        ];
        $data=[
            'type'=>$callType,
            'id'=>$id,
            'room_id'=>$room_id,
            'app_data'=>$app_data,
            'current_user'=>$userObj
        ];
        if($user->teacher && $user->teacher->id){
            $teacher_id=$user->teacher->id;
            $appointment = BookAppointment::withAll()->where('id', $id)->where('teacher_id', $teacher_id)->first();
            if ($appointment) {
                $title='Tutor as started the call.';
                $body='Please join the call now.';
                $deep_link = env('APP_URL') . '/call/join/'.$id.'?user_id='.$appointment->student_id.'&type=student';
                \App\Services\NotificationService::sendNotification($appointment->student, 'student', $title, $body, $deep_link, ['appointment_id' => $appointment->id]);
            }
        }

        return view('call',$data);
    }

    public function index($id,Request $request){
        $user = Auth()->user();
        $userObj=[];
        $userObj['id']=$user->id;
        $userObj['first_name']=$user->first_name;
        $userObj['last_name']=$user->last_name;
        $userObj['role']='user';
        $userObj['profile_image'] =asset('assets/images/dashboard-image-1.png');
        
        $appointment = BookAppointment::withAll()->where('id', $id)->first();
        $start = Carbon::parse($appointment->date)->setTimeFromTimeString($appointment->start_time);
        $formattedStart = $start->format('Y-m-d H:i:s');
        $end = Carbon::parse($appointment->date)->setTimeFromTimeString($appointment->end_time);
        $durationInMinutes = $start->diffInMinutes($end);         
        if($request->session()->get('logged_in_as') == 'academy'){
             $userObj['role']='academy';
        }
        if($request->session()->get('logged_in_as') == 'teacher'){
             $userObj['role']='teacher';
            $userObj['first_name']=$user->teacher->first_name;
            $userObj['last_name']=$user->teacher->last_name;
           $userObj['profile_image'] = $user->teacher->image ? asset($user->teacher->image) : asset('assets/images/dashboard-image-1.png'); 
            
        }
        if($request->session()->get('logged_in_as') == 'student'){
             $userObj['role']='student';
            $userObj['first_name']=$user->student->first_name;
            $userObj['last_name']=$user->student->last_name;
            $userObj['profile_image'] = $user->student->image ? asset($user->student->image) : asset('assets/images/dashboard-image-1.png'); 
        }
        $type='booking';
        $room_id='ot_'.$type.'_'.$id;
        $app_data=[
            'id'=>$id,
            'meeting_id'=>$id,
            'external_id'=>$id,
            'type'=>'booking',
            'title'=>'Meet Now',
            'room_id'=>$room_id,
            'start_time'=>$formattedStart,
            'duration_minutes'=>$durationInMinutes
        ];
        $data=[
            'type'=>$type,
            'id'=>$id,
            'room_id'=>$room_id,
            'app_data'=>$app_data,
            'current_user'=>$userObj
        ];
         if($user->teacher && $user->teacher->id){
            $teacher_id=$user->teacher->id;
            $appointment = BookAppointment::withAll()->where('id', $id)->where('teacher_id', $teacher_id)->first();
            if ($appointment) {
                $title='Tutor as started the call.';
                $body='Please join the call now.';
                //$deep_link = env('APP_URL') . '/call/join/'.$id.'?user_id='.$appointment->student_id.'&type=student';
                $deep_link = env('APP_URL') . '/appointment_log/detail/'.$id;
              
                \App\Services\NotificationService::sendNotification($appointment->student, 'student', $title, $body, $deep_link, ['appointment_id' => $appointment->id]);
            }
        }

        return view('call',$data);
    }

}
