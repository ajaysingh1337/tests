<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Fund;
use App\Models\FundBankTransfer;
use App\Models\Gateway;
use App\Models\User;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // $this->middleware('verified');
    }

    public function index(Request $req)
    {
        $user = auth()->user();

        $gateways = Gateway::where('status', 1)->orderBy('sort_by', 'ASC')->get();

        $fund_bank_transfers = FundBankTransfer::withAll()->whereHas('fund', function ($q) use ($user) {
            $q->where('status', 0)->where('type', 'wallet')->where('user_id', $user->id);
        })->get();

        $user = Auth::user();
        $balance = $user->wallet->balance;

        $transactions = $user->transactions()->with('fund')->orderBy('id', 'desc');
        $withdrawals = $user->withdrawals()->orderBy('id', 'desc');
        if ($req != null && $req->perPage) {
            $transactions =  $transactions->paginate($req->perPage);
            $withdrawals =  $withdrawals->paginate($req->perPage);
        } else {
            $transactions =  $transactions->paginate(10);
            $withdrawals =  $withdrawals->paginate(10);
        }

        // dd($transactions , $withdrawals);
        return Inertia::render('Wallet', [
            'current_balance' => $balance,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'gateways' => $gateways,
            'fund_bank_transfers' => $fund_bank_transfers,
        ]);
    }

    public function AddAmountToWallet(Request $request)
    {
        $request->validate(['gateway' => 'required|exists:gateways,code']);
        $request->merge(['type' => 'wallet']);

        if ($request->gateway == 'bank-transfer') {

            $fund_request = PaymentController::addFundRequest($request);

            // Check if the request was successful (returns array) or failed (returns JsonResponse)
            if (is_array($fund_request) && ($fund_request['fund'] ?? false)) {
                $data['fund_id'] = $fund_request['fund']['id'] ?? null;
                // dd($fund_request['fund']);
                $data['amount'] = $request->amount;
                return redirect(route('add_fund_bank_account', ['data' => $data]));
            } else {
                // Handle error case - extract error message from JsonResponse or handle array
                $errorMessage = 'Payment request failed';

                if (is_array($fund_request)) {
                    // Handle array responses (like validation errors)
                    if (is_string($fund_request)) {
                        $errorMessage = $fund_request;
                    } else {
                        // Convert array to readable message
                        $errorMessage = implode(', ', array_map(function ($key, $value) {
                            if (is_array($value)) {
                                return $key . ': ' . implode(', ', $value);
                            }
                            return $key . ': ' . $value;
                        }, array_keys($fund_request), array_values($fund_request)));
                    }
                } elseif (method_exists($fund_request, 'getData')) {
                    // Handle JsonResponse - PaymentController returns JSON with 'error' key
                    $errorData = $fund_request->getData(true);
                    $errorMessage = $errorData['error'] ?? 'Payment request failed';
                } elseif (method_exists($fund_request, 'getContent')) {
                    // Handle other response types
                    $content = $fund_request->getContent();
                    if (is_string($content)) {
                        $errorMessage = $content;
                    } else {
                        $decoded = json_decode($content, true);
                        if ($decoded && isset($decoded['error'])) {
                            $errorMessage = $decoded['error'];
                        }
                    }
                }

                request()->session()->flash('alert', [
                    'type' => 'error',
                    'message' => $errorMessage,
                ]);
                return redirect()->back()->withErrors(['error' => $errorMessage]);
            }
        }
        if ($request->gateway == 'stripe') {
            $fund_request = PaymentController::addFundRequest($request);

            // Check if the request was successful (returns array) or failed (returns JsonResponse)
            if (is_array($fund_request) && ($fund_request['fund'] ?? false)) {
                $data['fund_id'] = $fund_request['fund']['id'] ?? null;
                $data['amount'] = $request->amount;

                $fund = Fund::where('id', $data['fund_id'])->first();
                $default_currency = Currency::where('is_default', 1)->first();
                // dd($data['fund_id']);
                return Inertia::location(route('students.appointment_stripe_transfers_for_wallet', ['fund_id' => $data['fund_id']]));
            } else {
                // Handle error case - extract error message from JsonResponse or handle array
                $errorMessage = 'Payment request failed';

                if (is_array($fund_request)) {
                    // Handle array responses (like validation errors)
                    if (is_string($fund_request)) {
                        $errorMessage = $fund_request;
                    } else {
                        // Convert array to readable message
                        $errorMessage = implode(', ', array_map(function ($key, $value) {
                            if (is_array($value)) {
                                return $key . ': ' . implode(', ', $value);
                            }
                            return $key . ': ' . $value;
                        }, array_keys($fund_request), array_values($fund_request)));
                    }
                } elseif (method_exists($fund_request, 'getData')) {
                    // Handle JsonResponse - PaymentController returns JSON with 'error' key
                    $errorData = $fund_request->getData(true);
                    $errorMessage = $errorData['error'] ?? 'Payment request failed';
                } elseif (method_exists($fund_request, 'getContent')) {
                    // Handle other response types
                    $content = $fund_request->getContent();
                    if (is_string($content)) {
                        $errorMessage = $content;
                    } else {
                        $decoded = json_decode($content, true);
                        if ($decoded && isset($decoded['error'])) {
                            $errorMessage = $decoded['error'];
                        }
                    }
                }

                request()->session()->flash('alert', [
                    'type' => 'error',
                    'message' => $errorMessage,
                ]);
                return redirect()->back()->withErrors(['error' => $errorMessage]);
            }
        }

        $fund_request = PaymentController::addFundRequest($request);
        if ($fund_request['fund'] ?? false) {
            request()->session()->flash('alert', [
                'type' => 'info',
                'message' => 'Deposit Request Created Successfully',
            ]);
            return redirect()->back()->withResponseData([
                'fund' => $fund_request['fund']
            ]);
        } else {
            // Handle error case - extract error message from JsonResponse or handle array
            $errorMessage = 'Payment request failed';

            if (is_array($fund_request)) {
                // Handle array responses (like validation errors)
                if (is_string($fund_request)) {
                    $errorMessage = $fund_request;
                } else {
                    // Convert array to readable message
                    $errorMessage = implode(', ', array_map(function ($key, $value) {
                        if (is_array($value)) {
                            return $key . ': ' . implode(', ', $value);
                        }
                        return $key . ': ' . $value;
                    }, array_keys($fund_request), array_values($fund_request)));
                }
            } elseif (method_exists($fund_request, 'getData')) {
                // Handle JsonResponse - PaymentController returns JSON with 'error' key
                $errorData = $fund_request->getData(true);
                $errorMessage = $errorData['error'] ?? 'Payment request failed';
            } elseif (method_exists($fund_request, 'getContent')) {
                // Handle other response types
                $content = $fund_request->getContent();
                if (is_string($content)) {
                    $errorMessage = $content;
                } else {
                    $decoded = json_decode($content, true);
                    if ($decoded && isset($decoded['error'])) {
                        $errorMessage = $decoded['error'];
                    }
                }
            }

            request()->session()->flash('alert', [
                'type' => 'error',
                'message' => $errorMessage,
            ]);
            return redirect()->back()->withErrors(['error' => $errorMessage]);
        }

        request()->session()->flash('alert', [
            'type' => 'error',
            'message' => $errorMessage,
        ]);
        return redirect()->back()->withErrors(['error' => $errorMessage]);
    }

    public function withdrawAmount(Request $request)
    {
        $request->validate([
            'amount' => 'required',
            'account_holder' => 'required',
            'account_number' => 'required',
            'bank' => 'required',
            'additional_note' => 'required',
        ]);
        $user = Auth::user();
        if ($user->balanceInt > $request->amount || $user->balanceInt == $request->amount) {
            $created = WithdrawRequest::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'account_holder' => $request->account_holder,
                'account_number' => $request->account_number,
                'bank' => $request->bank,
                'additional_note' => $request->additional_note,
                'status' => WithdrawRequest::$Pending

            ]);
            request()->session()->flash('alert', [
                'type' => 'info',
                'message' => 'Withdraw request has been submitted',
            ]);
            return redirect()->back();
        } else {
            request()->session()->flash('alert', [
                'type' => 'error',
                'message' => 'Withdraw amount cannot be greater than Current Balance',
            ]);
            return redirect()->back();
        }
    }
    public function payThroughUserWallet($amount)
    {
        $user = Auth::user();
        if ($user->balanceInt > 0 && $user->balanceInt > $amount || $user->balanceInt == $amount) {
            $meta = ['details' => 'Appointment Booked through Wallet'];
            $transaction = $user->withdraw($amount, $meta);
            $obj = ["status" => true, "msg" => "Successfully withdraw from Wallet"];
        } else {

            $obj = ["status" => false, "msg" => "Insufficient Funds in your Wallet"];
        }
        return response()->json($obj);
    }
}
