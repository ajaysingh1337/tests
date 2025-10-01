<?php

namespace App\Services\Gateway\stripe;

use App\Models\Fund;
use Facades\App\Services\BasicService;
use Illuminate\Support\Facades\Log;

class Payment
{
    public static function prepareData($order, $gateway)
    {

        $basic = (object)config('basic');
        \Stripe\Stripe::setApiKey($gateway->parameters->secret_key ?? '');

        try {
            $checkoutSession = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'name' => optional($order->user)->username ?? $basic->site_title,
                    'description' => 'Payment with Stripe',
                    'images' => [asset('assets/uploads/logo/admin-logo.png')],
                    'amount' => round($order->final_amount, 2) * 100,
                    'currency' => $order->gateway_currency,
                    'quantity' => 1,
                ]],
                'cancel_url' => route('failed'),
                'success_url' => route('ipn', ['code' => 'stripe', 'trx' => $order->transaction]),
            ]);
        } catch (\Exception $e) {
            $send['error'] = true;
            $send['message'] = $e->getMessage();
            return json_encode($send);
        }

        if ($checkoutSession) {
            $order->btc_wallet = @json_decode(json_encode($checkoutSession))->id;
            $order->save();
        }

        $send['view'] = 'user.payment.stripe';
        $send['checkoutSession'] = $checkoutSession;
        $send['publishable_key'] = $gateway->parameters->publishable_key ?? '';



        return json_encode($send);
    }

    public static function ipn($request, $gateway, $order = null, $trx = null, $type = null)
    {
        \Stripe\Stripe::setApiKey($gateway->parameters->secret_key);

        $endpoint_secret = $gateway->parameters->endpoint_secret; // main
        // ddd($endpoint_secret);
        $payload = @file_get_contents('php://input');
        $event = null;

        
        if (!isset($payload) || empty($payload)) {
            Log::info("STRIPE PAYLOAD NOT EXISTS");
            http_response_code(400);
            exit();
        }
        Log::info("STRIPE PAYLOAD EXISTS");

        try {
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        }
        Log::info("STRIPE EVENT:".json_encode($event));
        if (isset($endpoint_secret) && !empty($endpoint_secret)) {
            $STRIPE_SIGNATURE = $_SERVER['HTTP_STRIPE_SIGNATURE'];
            Log::info("STRIPE SIGNATURE:".$STRIPE_SIGNATURE);
            try {
                $event = \Stripe\Webhook::constructEvent(
                    $payload,
                    $STRIPE_SIGNATURE,
                    $endpoint_secret
                );
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                // Invalid signature
                http_response_code(400);
                exit();
            }
        }
        Log::info("STRIPE EVENT:".json_encode($event));

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $session = $event->data->object;
                $order = Fund::where('btc_wallet',  $session->id)->orderBy('id', 'DESC')->first();
                \App\Services\BasicService::preparePaymentUpgradation($order);
                break;
            case 'checkout.session.completed':
                $session = $event->data->object;
                $order = Fund::where('btc_wallet',  $session->id)->orderBy('id', 'DESC')->first();
                \App\Services\BasicService::preparePaymentUpgradation($order);
                break;
            default:
                // Unexpected event type
                error_log('Received unknown event type');
        }
        http_response_code(200);
    }
}
