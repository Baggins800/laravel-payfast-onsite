<?php

namespace FintechSystems\Payfast\Http\Controllers;

use Exception;
use FintechSystems\Payfast\Cashier;
use FintechSystems\Payfast\Events\PaymentSucceeded;
use FintechSystems\Payfast\Events\SubscriptionCancelled;
use FintechSystems\Payfast\Events\SubscriptionCreated;
use FintechSystems\Payfast\Events\SubscriptionFetched;
use FintechSystems\Payfast\Events\SubscriptionPaymentSucceeded;
use FintechSystems\Payfast\Events\WebhookHandled;
use FintechSystems\Payfast\Events\WebhookReceived;
use FintechSystems\Payfast\Exceptions\InvalidMorphModelInPayload;
use FintechSystems\Payfast\Exceptions\MissingSubscription;
use FintechSystems\Payfast\Facades\Payfast;
use FintechSystems\Payfast\Receipt;
use FintechSystems\Payfast\Subscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{

    public function validServerConfirmation( $pfParamString, $pfHost = 'sandbox.payfast.co.za', $pfProxy = null )
    {
        // Use cURL (if available)
        if( in_array( 'curl', get_loaded_extensions(), true ) ) {
            // Variable initialization
            $url = 'https://'. $pfHost .'/eng/query/validate';

            // Create default cURL object
            $ch = curl_init();

            // Set cURL options - Use curl_setopt for greater PHP compatibility
            // Base settings
            curl_setopt( $ch, CURLOPT_USERAGENT, NULL );  // Set user agent
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );      // Return output as string rather than outputting it
            curl_setopt( $ch, CURLOPT_HEADER, false );             // Don't include header in output
            curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

            // Standard settings
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $pfParamString );
            if( !empty( $pfProxy ) )
                curl_setopt( $ch, CURLOPT_PROXY, $pfProxy );

            // Execute cURL
            $response = curl_exec( $ch );
            curl_close( $ch );
            if ($response === 'VALID') {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle a Payfast webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function __invoke(Request $request)
    {
        header( 'HTTP/1.0 200 OK' );
        flush();

        Log::info("Incoming Webhook from Payfast...");

        ray('Incoming Webhook from Payfast')->purple();

        $payload = $request->all();

        ray($payload)->blue();

        Log::debug($payload);

        $this->validServerConfirmation($request->all());
        if (! Payfast::isValidNotification($payload)) {
            return new Response('Invalid Data', 500);
        }

        try {
            if (! isset($payload['token'])) {
                $this->nonSubscriptionPaymentReceived($payload);
                WebhookHandled::dispatch($payload);

                return new Response('Webhook nonSubscriptionPaymentReceived handled', 200);
            }

            if (! $this->findSubscription($payload['token'])) {
                $this->createSubscription($payload);
                WebhookHandled::dispatch($payload);

                return new Response('Webhook createSubscription/applySubscriptionPayment handled', 200);
            }

            if ($payload['payment_status'] == Subscription::STATUS_DELETED) {
                $this->cancelSubscription($payload);
                WebhookHandled::dispatch($payload);

                return new Response('Webhook cancelSubscription handled', 200);
            }

            if ($payload['payment_status'] == Subscription::STATUS_COMPLETE) {
                $this->applySubscriptionPayment($payload);
                WebhookHandled::dispatch($payload);

                return new Response('Webhook applySubscriptionPayment handled');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();

            Log::critical($message);

            ray($e)->red();

            return response('An exception occurred in the PayFast webhook controller', 500);
        }
    }

    /**
     * Handle one-time payment succeeded.
     *
     * @param  array  $payload
     * @return void
     */
    protected function nonSubscriptionPaymentReceived(array $payload)
    {
        $message = "Creating a non-subscription payment receipt...";

        Log::info($message);

        ray($message)->orange();

        $receipt = Receipt::create([
            'merchant_payment_id' => $payload['m_payment_id'],
            'payfast_payment_id' => $payload['pf_payment_id'],
            'payment_status' => $payload['payment_status'],
            'item_name' => $payload['item_name'],
            'item_description' => $payload['item_description'],
            'amount_gross' => $payload['amount_gross'],
            'amount_fee' => $payload['amount_fee'],
            'amount_net' => $payload['amount_net'],
            'billable_id' => $payload['custom_int1'],
            'billable_type' => base64_decode($payload['custom_str1']),
            'paid_at' => now(),
        ]);

        PaymentSucceeded::dispatch($receipt, $payload);

        $message = "Created the non-subscription payment receipt.";

        Log::notice($message);

        ray($message)->green();
    }

    protected function createSubscription(array $payload)
    {
        $message = "Creating a new subscription...";

        Log::info($message);

        ray($message)->orange();

        $customer = $this->findOrCreateCustomer($payload);

        $subscription = $customer->subscriptions()->create([
            'payfast_token' => $payload['token'],
            'plan_id' => $payload['custom_int2'],
            'name' => 'default', // See Laravel Cashier Stripe and Paddle docs - "internal name of the subscription"
            'payfast_status' => $payload['payment_status'],
            'next_bill_at' => $payload['billing_date'],
        ]);

        SubscriptionCreated::dispatch($customer, $subscription, $payload);

        $message = "Created a new subscription " . $payload['token'] . ".";

        Log::notice($message);

        ray($message)->green();

        $this->applySubscriptionPayment($payload);
    }

    /**
     * Apply a subscription payment succeeded.
     *
     * Gets triggered after first payment, and every subsequent payment that has a token
     *
     * @param  array  $payload
     * @return void
     */
    protected function applySubscriptionPayment(array $payload)
    {
        if (is_null($payload['item_name'])) {
            $payload['item_name'] = 'Card Information Updated';
            $message = "Updating card information for " . $payload['token'] . "...";
        } else {
            $message = "Applying a subscription payment to " . $payload['token'] . "...";
        }

        Log::info($message);
        ray($message)->orange();

        $billable = $this->findSubscription($payload['token'])->billable;

        $receipt = $billable->receipts()->create([
            'payfast_token' => $payload['token'],
            'order_id' => $payload['m_payment_id'],
            'merchant_payment_id' => $payload['m_payment_id'],
            'payfast_payment_id' => $payload['pf_payment_id'],
            'payment_status' => $payload['payment_status'],
            'item_name' => $payload['item_name'],
            'item_description' => $payload['item_description'] ?? null,
            'amount_gross' => $payload['amount_gross'],
            'amount_fee' => $payload['amount_fee'],
            'amount_net' => $payload['amount_net'],
            'billable_id' => $payload['custom_int1'],
            'billable_type' => base64_decode($payload['custom_str1']),
            'paid_at' => now(),
        ]);

        SubscriptionPaymentSucceeded::dispatch($billable, $receipt, $payload);

        if ($payload['item_name'] == 'Card Information Updated') {
            $message = "Updated the card information.";
        } else {
            $message = "Applied the subscription payment.";
        }
        Log::notice($message);
        ray($message)->green();

        $message = "Fetching and updating API status for token " . $payload['token'] . "...";
        Log::info($message);
        ray($message)->orange();

        // Dispatch a new API call to fetch the subscription information and update the status and next_bill_at
        $result = Payfast::fetchSubscription($payload['token']);

        Log::debug("Result of new API call to get current subscription status and next_bill_at");
        Log::debug($result);
        ray($result);

        $subscription = Subscription::wherePayfastToken($payload['token'])->first();
        if ($subscription) {
          $subscription->updatePayfastSubscription($result);
        }


        $message = "Fetched and updated API status for token " . $payload['token'] . ".";
        Log::notice($message);
        ray($message)->green();

        // PayFast requires a 200 response after a successful payment application
        return response('Subscription Payment Applied', 200);
    }

    protected function fetchSubscriptionInformation(array $payload)
    {
        $message = "Fetching subscription information for " . $payload['token'] . "...";
        Log::info($message);
        ray($message)->orange();

        $result = Payfast::fetchSubscription($payload['token']);

        // Update or Create Subscription
        $subscription = Subscription::find(1);

        ray($result);

        SubscriptionFetched::dispatch($subscription, $payload);
    }

    /**
     * Handle subscription cancelled.
     *
     * @param  array  $payload
     * @return void
     */
    protected function cancelSubscription(array $payload)
    {
        $message = "Cancelling subscription " . $payload['token'] . "...";
        Log::info($message);
        ray($message)->orange();

        if (! $subscription = $this->findSubscription($payload['token'])) {
            throw new MissingSubscription();
        }

        $message = "Looked for and found the subscription...";
        Log::debug($message);
        ray($message);

        // ray($subscription);

        // Cancellation date...
        if (is_null($subscription->ends_at)) {
            $subscription->ends_at = $subscription->onTrial()
                ? $subscription->trial_ends_at
                : $subscription->next_bill_at->subMinutes(1);
        }

        // $message = "We've interpreted and possibly saved the ends_at date...";
        // Log::debug($message);
        // ray($message);

        $subscription->cancelled_at = now();

        $subscription->payfast_status = $payload['payment_status'];

        $subscription->paused_from = null;

        // $message = "Now we're going to save information about the subscription...";
        // Log::debug($message);
        // ray($message);

        $subscription->save();

        SubscriptionCancelled::dispatch($subscription, $payload);

        $message = "Cancelled the subscription.";
        Log::notice($message);
        ray($message)->green();
    }

    private function findSubscription(string $subscriptionId)
    {
        return Cashier::$subscriptionModel::firstWhere('payfast_token', $subscriptionId);
    }

    private function findOrCreateCustomer(array $passthrough)
    {
        if (! isset($passthrough['custom_str1'], $passthrough['custom_int1'])) {
            throw new InvalidMorphModelInPayload(base64_decode($passthrough['custom_str1']) . "|" . $passthrough['custom_int1']);
        }

        return Cashier::$customerModel::firstOrCreate([
            'billable_id' => $passthrough['custom_int1'],
            'billable_type' => base64_decode($passthrough['custom_str1']),
        ])->billable;
    }
}
