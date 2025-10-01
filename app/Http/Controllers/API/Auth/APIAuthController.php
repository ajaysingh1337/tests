<?php

namespace App\Http\Controllers\API\Auth;

use App\FCM\GCMService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Http\Resources\API\UsersResource;
use App\Mail\User\Auth\ForgotPasswordEmail;
use App\Models\GeneralSetting;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Auth\ResetPassword;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Session;

class APIAuthController extends Controller
{
    protected $gcmService;

    public function __construct(GCMService $gcmService)
    {
        $this->middleware(['api' , 'api_setting']);
        $this->middleware(['auth:api'])->only(['logout','getLoggedInUser']);
        // $this->middleware('guest')->except(['logout']);
        $this->gcmService = $gcmService;
    }

    public function submitForgotPasswordForm(Request $request)
    {
        $request->validate(
            [
                'email' => 'required|email|exists:users,email'
            ]
        );

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('password_resets')->where('email', $request->email)->delete(); // revoke previous tokens
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => Carbon::now()
            ]);
            $user->update(['forgot_pass_token' => $token]);
            Notification::send($user, new ResetPassword($token));
            $response = generateResponse(null,true,"Email Sent Successfully Please Check Your Inbox!",null,'collection');
        }else{
            $response = generateResponse(null,false,"User Not Found",null,'collection');
        }
        return response()->json($response);
    }

    public function submitResetPasswordForm(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed',
            'token' => 'required|exists:password_resets,token',
        ]);
        $password_reset = DB::table('password_resets')->where('token', $request->token)->first();
        if ($password_reset) {
            $user = User::where('email', $password_reset->email)->first();
            if ($user) {
                $user_data = [];
                $user_data['password'] = Hash::make($request->password);
                $user->update($user_data);
                DB::table('password_resets')->where('email', $user->email)->delete();
                $response = generateResponse(null,true,"Password Resets Successfully",null,'collection');
            }
            $response = generateResponse(null,false,"Invalid Token Provided",null,'collection');

        } else {
            $response = generateResponse(null,false,"Invalid Token Provided",null,'collection');
        }
        return response()->json($response);
    }

    public function submitLoginForm(LoginRequest $request)
    {
        $email = $request->email;
        $password = $request->password;
        
        Log::info('Login attempt', [
            'email' => $email,
        ]);

        $user = User::with('teacher', 'student', 'academy')->where('email', $email)->first();

        if (!$user) {
            return response()->json(generateResponse(null, false, "The provided email and password do not match", null, 'collection'));
        }

        // Check user role
        $roleCheck = [
            'student' => Role::$Student,
            'teacher' => Role::$Teacher,
            'academy' => Role::$Academy
        ][$request->login_as] ?? null;

        if ($roleCheck && !$user->hasRole($roleCheck)) {
            return response()->json(generateResponse(null, false, "The provided email and password do not match", null, 'collection'));
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json(generateResponse(null, false, "The provided email and password do not match", null, 'collection'));
        }

        // Login successful
        $request->session()->put('logged_in_as', $request->login_as);
        // $user = $user->fresh();

        // Prepare response
        $success['user'] = new UsersResource($user);
        $token = $user->createToken('MyApp', []);
        $success['token'] = $token->accessToken;

        return response()->json(generateResponse($success, true, "Successfully logged in", null, 'collection'));
    }

    public function submitRegisterForm(Request $request)
    {
        if ($request->login_as == 'teacher') {
            $user_name_rule = 'required|alpha_dash|max:55|unique:teachers,user_name';
        } else if ($request->login_as == 'academy') {
            $user_name_rule = 'required|string|max:55|unique:academies,user_name';
        } else if ($request->login_as == 'student') {
            $user_name_rule = 'required|alpha_dash|max:55|unique:students,user_name';
        } else {
            $user_name_rule = 'required|alpha_dash|max:55|unique:teachers,user_name';
        }
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|unique:users,email',
            'password' => 'required|min:8' . ($request->has('password_confirmation') ? '|confirmed' : ''),
            'user_name' => $user_name_rule,
        ]);
        
        if ($validator->fails()) {
            Log::error('Validation failed', [
                'errors' => $validator->errors()->all(),
                'input' => $request->except('password', 'password_confirmation') // Exclude passwords from logs
            ]);
            
            // Return the validation errors
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        $data = $request->all();
        $data['name'] = $data['first_name'].' '.$data['last_name'];
        $data['password'] = Hash::make($request->password);
        $data['is_active'] = 1;
        $user = User::create($data);
        $user->roles()->attach([$request->login_as]);
        if($request->login_as == 'teacher'){
            $pricing_plan = getTeacherDefaultPricingPlan();
            $user->teacher()->create(['pricing_plan_id' => $pricing_plan->id ?? null,'first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null,  'user_name' => $data['user_name'] ?? null]);
        }
        if($request->login_as == 'student'){
            $user->student()->create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null,  'user_name' => $data['user_name'] ?? null]);
        }
        if($request->login_as == 'academy'){
            $pricing_plan = getAcademyDefaultPricingPlan();
            $user->academy()->create(['pricing_plan_id' => $pricing_plan->id ?? null,'first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null,  'user_name' => $data['user_name'] ?? null]);
        }
        // $user->sendEmailVerificationNotification();
        $user->markEmailAsVerified();

        if ($user) {
            $user = User::with('teacher', 'student', 'academy')->where('id', $user->id)->first();
            $success['user'] = new UsersResource($user);
            $token = $user->createToken('MyApp',[]);
            $success['token'] =  $token->accessToken;
            $response = generateResponse($success,true,"Successfully Login",null,'collection');
        } else {
            $response = generateResponse(null,false,"Invalid Request",null,'collection');

        }
        return response()->json($response);

    }

    public function getLoggedInUser(){
        $user = Auth::user();
        $user = User::where('id',$user->id)->withAll()->first();
        $user = new UsersResource($user);
        $response = generateResponse($user,true,"Successfully Login",null,'collection');
        return response()->json($response);

    }

    public function deleteAccount()
    {
        DB::beginTransaction();

        try {
            $user = Auth::user();
            $userTokens = $user->tokens;
            foreach ($userTokens as $token) {
                $token->revoke();
            }
            // Revoke all tokens
            $user->tokens->each(function($token) {
                $token->revoke();
            });
            
            // Clear sessions
            DB::table('sessions')->where('user_id', $user->id)->delete();
            
            // Delete user
            $user->delete();
            DB::commit();

            return response()->json(generateResponse(null, true, "Account Deleted Successfully", null, 'collection'));
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(generateResponse(null, false, "Account Deletion Failed: " . $e->getMessage(), null, 'collection'), 500);
        }
    }

    public function socialLogin(SocialLoginRequest $request){
        try {
             DB::beginTransaction();
              $data = $request->only('email' , 'first_name' , 'login_as' , 'last_name');
              $data['name'] = $data['first_name'] ?? '-'.' '.$data['last_name'] ?? '-';
              $data['is_active'] = 1;
              $user = User::where('email' , $request->email)->first();
              if($user){
                $response['is_login'] = 1;
                $response = $this->loginUser($user ,$request, $data);
              }else{
                $user = User::create($data);
                $user->roles()->attach([$request->login_as]);
                if($request->login_as == 'teacher'){
                    $pricing_plan = getTeacherDefaultPricingPlan();
                    $user->teacher()->create(['pricing_plan_id' => $pricing_plan->id ?? null,'first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null]);
                }
                if($request->login_as == 'student'){
                    $user->student()->create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null]);
                }
                if($request->login_as == 'academy'){
                    $pricing_plan = getAcademyDefaultPricingPlan();
                    $user->academy()->create(['pricing_plan_id' => $pricing_plan->id ?? null,'first_name' => $data['first_name'], 'last_name' => $data['last_name'],'zip_code' => $data['zip_code'] ?? null]);
                }
                $response['is_login'] = 0 ;
                $data['is_social'] = 1;
                $response = $this->loginUser($user , $request , $response);
                DB::commit();
              }
              return response()->json($response, 200);
        } catch (\Exception $e) {
          DB::rollback();
          $response = generateResponse(null,false,$e->getMessage(),null,'object');
          return response()->json($response, 200);
        }

     }
     public function loginUser($user ,$request, $data){
        $user = User::where('id',$user->id)->withAll()->first();
        $success['user'] = new UsersResource($user);
        $token = $user->createToken('MyApp',[]);
        $success['token'] =  $token->accessToken;
        $request->session()->put('logged_in_as', $data['login_as'] ?? 'student');
        $response = generateResponse($success,true,"Successfully Login",null,'collection');
        return $response;
    }

    public function logout(Request $request)
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                
                // Revoke all user tokens
                $user->tokens->each(function($token) {
                    $token->revoke();
                });
                
                // Clear session
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            
            $response = generateResponse([], true, 'User successfully logged out', [], 'object');
            return response()->json($response, 200);
        } catch (\Exception $e) {
            $response = generateResponse(null,false,$e->getMessage(),null,'object');
            return response()->json($response, 200);
        }
    }
}
