<?php

namespace FintechSystems\Payfast;

use Carbon\Carbon;
use FintechSystems\Payfast\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PayFast\PayFastApi;
use PayFast\PayFastPayment;
use FintechSystems\Payfast\Plan;
use FintechSystems\Payfast\Subscription;

class Payfast implements PaymentGateway
{
    private $payment;

    private $returnUrl;
    private $cancelUrl;
    private $notifyUrl;
    private $urlCollection;

    private $cardUpdateLinkCss;

    private $cardUpdatedReturnUrl;

    public function __construct($client)
    {
        $this->payment = new PayFastPayment(
            [
                'merchantId' => $client['merchant_id'],
                'merchantKey' => $client['merchant_key'],
                'passPhrase' => $client['passphrase'],
                'testMode' => $client['testmode'],
            ]
        );

        $this->api = new PayFastApi(
            [
                'merchantId' => $client['merchant_id'],
                'passPhrase' => $client['passphrase'],
                'testMode' => $client['testmode'],
                'custom_str1' => 'api',
            ]
        );

        $this->returnUrl = $client['return_url'];
        $this->cancelUrl = $client['cancel_url'];
        $this->notifyUrl = $client['notify_url'];
        $this->urlCollection = [
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'notify_url' => $this->notifyUrl,
        ];

        $this->cardUpdateLinkCss = $client['card_update_link_css'];

        $this->cardUpdatedReturnUrl = $client['card_updated_return_url'];
    }

    public function cancelSubscription(Subscription $subscription)
    {
        $cancelArray = $this->api->subscriptions->cancel($subscription->payfast_token);
        $valid = !Validator::make($cancelArray, [
          'data' => 'array:response,message',
          'status' => 'string',
          'code' => 'numeric'
        ])->fails();

        if ($valid && $cancelArray['data']['response']) {
          $subscription->cancelled_at = now();
          $subscription->payfast_status = "CANCELLED";
          $subscription->ends_at = $subscription->next_bill_at;
          $subscription->save();
        }

        ray($cancelArray);
        return $subscription;
    }

    public function isValidNotification(array $data)
    {
        return $this->payment->notification->isValidNotification($data);
    }

    /**
     * Create a new subscription
     */
    public function createSubscription(Plan $plan, $confirmationEmail=null, $cycles = 0)
    {
        $data = [
            'subscription_type' => 1,
            'm_payment_id' => Order::generate(),
            'amount' => $plan->initial_amount,
            'recurring_amount' => $plan->recurring_amount,
            'billing_date' => $plan->billing_date ? Carbon::createFromFormat('Y-m-d H:i:s', $plan->billing_date)->format('Y-m-d') : Carbon::now()->format('Y-m-d'),
            'frequency' => $plan->payfast_frequency,
            'cycles' => $cycles,
            'custom_str1' => rtrim(base64_encode(Auth::user()->getMorphClass()), '='),
            'custom_int1' => Auth::user()->getKey(),
            'custom_int2' => $plan->id,
            'custom_str2' => rtrim(base64_encode($plan->name), '='),
            'item_name' => config('app.name') . " $plan->name Subscription",
            'email_address' => Auth::user()->email,

        ];

        if ($confirmationEmail) {
            $data['confirmation_address'] = $confirmationEmail;
            $date['email_confirmation'] = 1;
        }

        return $this->payment->custom->createFormFields(
            array_merge($data, $this->urlCollection),
            [
                'value' => 'Create Subscription',
                'class' => $this->cardUpdateLinkCss,
            ]
        );
    }

    /**
     * Create a new subscription based on the Payfast "Onsite Payment" modal method
     *
     * https://developers.payfast.co.za/docs#onsite_payments
     */
    public function createOnsitePayment(Plan $plan, $billingDate = null, $mergeFields = [], $cycles = 0)
    {
        $recurringType = Subscription::frequencies($plan->payfast_frequency);

        ray("billingDate in createOnsitePayment: " . $billingDate);

        $data = [
            'subscription_type' => 1,
            'm_payment_id' => Order::generate(),
            'amount' => $plan->initial_amount,
            'recurring_amount' => $plan->recurring_amount,
            'billing_date' => $billingDate,
            'frequency' => $plan->payfast_frequency,
            'cycles' => $cycles,
            'custom_str1' => rtrim(base64_encode(Auth::user()->getMorphClass()), '='),
            'custom_int1' => Auth::user()->getKey(),
            'custom_int2' => $plan->id,
            'custom_str2' => rtrim(base64_encode($plan->name), '='),
            'item_name' => config('app.name') . " $recurringType Subscription",            
            'email_address' => Auth::user()->email,
            'confirmation_address' => Auth::user()->email,
	    'email_confirmation' => 1
        ];

        $data = array_merge($data, $this->urlCollection);

        if ($mergeFields) {
            $data = array_merge($data, $mergeFields);
        }

        $message = "The callback URL defined in createOnsitePayment is " . $data['notify_url'];

        ray($message);

        $message = "PayFast onsite payment modal was invoked with these merged values:";

        Log::debug($message);

        ray($message)->orange();

        Log::debug($data);

        ray($data)->orange();
        
        $identifier = $this->payment->onsite->generatePaymentIdentifier($data);
        
        if ($identifier !== null) {
            return $identifier;            
        }
    }

    /**
     * Set up an ad-hoc payment agreement
     *
     * https://developers.payfast.co.za/docs#tokenization
     */
    public function createToken($amount = 0)
    {
        $data = [
            'custom_str1' => 'subscription',
            'subscription_type' => 2,
            'm_payment_id' => 'new_tokenization_' . Auth::user()->getKey(),
            'item_name' => config('app.name') . ' Monthly Subscription',
            'amount' => $amount,
            'name_last' => Auth::user()->name,
            'email_address' => Auth::user()->email,
            'custom_str2' => Auth::user()->getMorphClass(),
            'custom_str3' => 'Monthly Subscription',
            'custom_int2' => Auth::user()->getKey(),
            'custom_int3' => 1, // Plan ID
            'custom_int4' => 1, // Quantity
        ];

        return $this->payment->custom->createFormFields(
            array_merge($data, $this->urlCollection),
            [
                'value' => 'Create Tokenization',
                'class' => $this->cardUpdateLinkCss,
            ]
        );
    }

    public function fetchSubscription($token)
    {
        $fetchArray = $this->api->subscriptions->fetch($token);

        ray($fetchArray);

        return $fetchArray;
    }

    public function payment($amount, $itemName)
    {
        $data = [
            'amount' => $amount,
            'item_name' => $itemName,
            'return_url' => $this->returnUrl,
            'cancel_url' => $this->cancelUrl,
            'notify_url' => $this->notifyUrl,
        ];

        echo $this->payment->custom->createFormFields(
            $data,
            [
                'value' => 'PAY NOW',
                'class' => 'btn',
            ]
        );
    }

    /**
     * Generate a credit card update link
     *
     * Add 'target' => '_blank' to open in new window
     */
    public function updateCardLink($token)
    {
        return $this->payment->custom->createCardUpdateLink(
            $token,
            $this->cardUpdatedReturnUrl,
            'Update Card',
            [
                'class' => $this->cardUpdateLinkCss,
            ]
        );
    }
}
