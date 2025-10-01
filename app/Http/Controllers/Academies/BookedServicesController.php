<?php

namespace App\Http\Controllers\Academies;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;

use App\Http\Resources\Web\BookedServicesResource;
use App\Models\ServiceStatus;
use App\Models\BookedService;
use App\Models\Commission;
use App\Services\NotificationService;
use Carbon\Carbon;

class BookedServicesController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
        // $this->middleware('academies');
    }
    public function getter($req = null, $export = null)
    {

        $academies = auth()->user()->academy;

        if ($req != null) {
            $academy_services =  $academies->services()->withAll();
            if ($req->trash && $req->trash == 'with') {
                $academy_services =  $academy_services->withTrashed();
            }
            if ($req->trash && $req->trash == 'only') {
                $academy_services =  $academy_services->onlyTrashed();
            }
            if ($req->column && $req->column != null && $req->search != null) {
                if (in_array($req->column, ['start_time', 'end_time'])) {
                    // Convert search time to 12-hour format to match DB format
                    $searchTime = date('h:i A', strtotime($req->search));
                    
                    if ($req->column === 'start_time') {
                        // For start_time, find appointments where start_time is >= search time
                        $academy_services = $academy_services->whereRaw("STR_TO_DATE(start_time, '%h:%i %p') >= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    } else {
                        // For end_time, find appointments where end_time is <= search time
                        $academy_services = $academy_services->whereRaw("STR_TO_DATE(end_time, '%h:%i %p') <= STR_TO_DATE(?, '%h:%i %p')", [$searchTime]);
                    }
                } else {
                    // Original behavior for other columns
                    $academy_services = $academy_services->whereLike($req->column, $req->search);
                }
            }
            else if ($req->search && $req->search != null) {

                $academy_services = $academy_services->whereLike(['name', 'description'], $req->search);
            }

            if ($req->status_code) {
                $academy_services = $academy_services->where('service_status_code', $req->status_code);
            }

            if ($req->sort && $req->sort['field'] != null && $req->sort['type'] != null) {
                $academy_services = $academy_services->OrderBy($req->sort['field'], $req->sort['type']);
            } else {
                $academy_services = $academy_services->OrderBy('id', 'desc');
            }
            if ($export != null) { // for export do not paginate
                $academy_services = $academy_services->get();
                return $academy_services;
            }
            $totalAcademyServices = $academy_services->count();
            $academy_services = $academy_services->paginate($req->perPage);
            $academy_services = BookedServicesResource::collection($academy_services)->response()->getData(true);

            return $academy_services;
        }
        $academy_services = BookedServicesResource::collection($academies->services()->withAll()->orderBy('id', 'desc')->paginate(10))->response()->getData(true);
        return $academy_services;
    }
    public function getAcademyFilteredServiceLogs(Request $request)
    {
        $services = $this->getter($request);
        $response = generateResponse($services, count($services['data']) > 0 ? true : false, 'Filter Service Logs Successfully', null, 'collection');
        return response()->json($response, 200);
    }
    public function showAcademyServiceLogDetailPage($id)
    {
        $user = Auth()->user();
        $academy_id = $user->academy->id;
        $service = BookedService::withAll()->where('id', $id)->where('academy_id', $academy_id)->first();
        $service = new BookedServicesResource($service);
        $data = [
            'service' => $service,
        ];
        return Inertia::render('ServiceLogs/Detail', $data);
    }
    public function updateServiceStatus(Request $request)
    {

        $settings = generalSettings();
        $user = Auth()->user();
        $academy_id = $user->academy->id;
        $service = BookedService::withAll()->where('id', $request->service_id)->where('academy_id', $academy_id)->first();
        if ($service) {
            $updated =  $service->update([
                'service_status_code' => $request->status_code
            ]);
            if ($request->status_code == ServiceStatus::$Completed) {
                $service->update([
                    'ended_at' => Carbon::now(),
                ]);
            }
            if ($updated) {
                if ($request->status_code == ServiceStatus::$Accepted) {
                    $title = 'Your Service has been Accepted';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/service_log';
                }
                if ($request->status_code == ServiceStatus::$Rejected) {
                    $title = 'Your Service has been Rejected';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/service_log';
                }
                if ($request->status_code == ServiceStatus::$Cancel) {

                    $title = 'Your Service has been Canceled';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/service_log';
                }
                if ($request->status_code == ServiceStatus::$Completed) {

                    $title = 'Your Service has been Completed';
                    $body = 'You have a new notification';
                    $deep_link = env('APP_URL') . '/service_log';
                    if ((int)$settings['enable_wallet_system']) {
                        if ($settings['commission_type'] == 'commission_base') {
                            $commission = Commission::where('service_type_id', $service->service_type_id)->first();
                            if ($commission && $commission->commission_type == 'fixed_rate') {
                                $commission_amount = $commission->rate ?? 0;
                                $final_amount = $service->price - $commission_amount;
                            } else {
                                $rate = $commission->rate ?? 0;
                                $percentage_value = ($service->price / 100) * $rate;
                                $commission_amount = $percentage_value;
                                $final_amount = $service->price - $percentage_value;
                            }
                        } else {
                            $final_amount = $service->price;
                        }
                        $meta = ['details' => 'Deposit on Completion of Service # ' . $service->id];

                        $user->deposit($final_amount, $meta);
                    }
                }
                \App\Services\NotificationService::sendNotification($service->student, 'student', $title, $body, $deep_link, ['service_id' => $service->id]);
            }


            if ($request->status_code == 2) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Service Accepted Successfully',
                ]);
            } elseif ($request->status_code == 3) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Service Rejected Successfully',
                ]);
            } elseif ($request->status_code == 5) {
                request()->session()->flash('alert', [
                    'type' => 'info',
                    'message' => 'Service Mark as Completed Successfully',
                ]);
            }
            return redirect()->back();
        }
    }
    public function updateServiceStarted(Request $request)
    {

        $user = Auth()->user();
        $academy_id = $user->academies->id;
        $service = BookedService::withAll()->where('id', $request->service_id)->where('academy_id', $academy_id)->first();
        if ($service) {
            $updated =  $service->update([
                'started_at' => Carbon::now(),
            ]);

            $response = generateResponse(null, true, 'Service Joined Successfully', null, 'object');
            return response()->json($response, 200);
        }
    }
}
