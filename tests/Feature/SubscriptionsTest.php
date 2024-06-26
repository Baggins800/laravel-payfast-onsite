<?php

namespace Tests\Feature;

use Carbon\Carbon;
use FintechSystems\Payfast\Facades\Payfast;

use FintechSystems\Payfast\Facades\PayFastApi;
use FintechSystems\Payfast\Subscription;
use Illuminate\Support\Facades\Http;
use LogicException;

class SubscriptionsTest extends FeatureTestCase
{
    public function test_cannot_swap_while_on_trial()
    {
        $subscription = new Subscription(['trial_ends_at' => now()->addDay()]);

        $this->expectExceptionObject(new LogicException('Cannot swap plans while on trial.'));

        $subscription->swap(123);
    }

    public function test_customers_can_perform_subscription_checks()
    {
        $billable = $this->createBillable();

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertTrue($billable->subscribed('main'));
        $this->assertFalse($billable->subscribed('default'));
        $this->assertFalse($billable->subscribedToPlan(2323));
        $this->assertTrue($billable->subscribedToPlan(2323, 'main'));
        $this->assertTrue($billable->onPlan(2323));
        $this->assertFalse($billable->onPlan(323));
        $this->assertFalse($billable->onTrial('main'));
        $this->assertFalse($billable->onGenericTrial());

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->paused());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertTrue($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }

    public function test_customers_can_check_if_they_are_on_a_generic_trial()
    {
        $billable = $this->createBillable('taylor', ['trial_ends_at' => Carbon::tomorrow()]);

        $this->assertTrue($billable->onGenericTrial());
        $this->assertTrue($billable->onTrial());
        $this->assertFalse($billable->onTrial('main'));
        $this->assertEquals($billable->trialEndsAt(), Carbon::tomorrow());
    }

    public function test_customers_can_check_if_their_subscription_is_on_trial()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => Carbon::tomorrow(),
        ]);

        $this->assertTrue($billable->subscribed('main'));
        $this->assertFalse($billable->subscribed('default'));
        $this->assertFalse($billable->subscribedToPlan(2323));
        $this->assertTrue($billable->subscribedToPlan(2323, 'main'));
        $this->assertTrue($billable->onPlan(2323));
        $this->assertFalse($billable->onPlan(323));
        $this->assertTrue($billable->onTrial('main'));
        $this->assertTrue($billable->onTrial('main', 2323));
        $this->assertFalse($billable->onTrial('main', 323));
        $this->assertFalse($billable->onGenericTrial());
        $this->assertEquals($billable->trialEndsAt('main'), Carbon::tomorrow());

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());
        $this->assertTrue($subscription->onTrial());
        $this->assertFalse($subscription->paused());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }

    public function test_customers_can_check_if_their_subscription_is_cancelled()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_DELETED,
            'ends_at' => Carbon::tomorrow(),
        ]);

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->paused());
        $this->assertTrue($subscription->cancelled());
        $this->assertTrue($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }

    public function test_customers_can_check_if_the_grace_period_is_over()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_DELETED,
            'ends_at' => Carbon::yesterday(),
        ]);

        $this->assertFalse($subscription->valid());
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->paused());
        $this->assertTrue($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertTrue($subscription->ended());
    }

    public function test_customers_can_check_if_the_subscription_is_paused()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_PAUSED,
        ]);

        $this->assertFalse($subscription->valid());
        $this->assertFalse($subscription->active());
        $this->assertFalse($subscription->onTrial());
        $this->assertTrue($subscription->paused());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }

    public function test_subscriptions_can_be_on_a_paused_grace_period()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => 244,
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_ACTIVE,
            'paused_from' => Carbon::tomorrow(),
        ]);

        $this->assertTrue($subscription->valid());
        $this->assertTrue($subscription->active());
        $this->assertFalse($subscription->onTrial());
        $this->assertFalse($subscription->paused());
        $this->assertFalse($subscription->cancelled());
        $this->assertFalse($subscription->onGracePeriod());
        $this->assertFalse($subscription->recurring());
        $this->assertFalse($subscription->ended());
    }

    public function test_subscriptions_can_fetch_their_subscription_info()
    {
        $billable = $this->createBillable('taylor');

        $subscription = $billable->subscriptions()->create([
            'name' => 'main',
            'payfast_token' => "a3b3ae55-ab8b-b388-df23-4e6882b86ce0",
            'plan_id' => 2323,
            'payfast_status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            // 'https://www.payfast.co.za/eng/process' => Http::response([
            'https://api.payfast.co.za*' => Http::response([
                'code' => 200,
                'status' => "success",
                'data' => [
                    'response' => [
                        [
                            'amount' => 599,
                            'cycles' => 0,
                            'cycles_complete' => 1,
                            'frequency' => 3,
                            'run_date' => "2022-06-29T00:00:00+02:00",
                            'status' => 1,
                            'status_reason' => "",
                            'status_text' => "ACTIVE",
                            'token' => "a3b3ae55-ab8b-b388-df23-4e6882b86ce0",
                        ],
                    ],
                ]
            ]),
        ]);

        $result = Payfast::fetchSubscription($subscription->payfast_token);

        ray($result);
    
        $this->assertSame('a3b3ae55-ab8b-b388-df23-4e6882b86ce0', $subscription->payfast_token);

        // $this->assertSame('john@example.com', $subscription->paddleEmail());
        // $this->assertSame('card', $subscription->paymentMethod());
        // $this->assertSame('visa', $subscription->cardBrand());
        // $this->assertSame('1234', $subscription->cardLastFour());
        // $this->assertSame('04/2022', $subscription->cardExpirationDate());
    }
}
