<?php

return [
    'merchant_id' => env('PAYFAST_MERCHANT_ID', '10004002'),
    'merchant_key' => env('PAYFAST_MERCHANT_KEY', 'q1cd2rdny4a53'),
    'passphrase' => env('PAYFAST_PASSPHRASE', 'payfast'),
    'testmode' => env('PAYFAST_TESTMODE', true),        
    'return_url' => env('PAYFAST_RETURN_URL', config('app.url') . '/payfast/success'),
    'cancel_url' => env('PAYFAST_CANCEL_URL', config('app.url') . '/payfast/cancel'),
    'notify_url' => env('PAYFAST_NOTIFY_URL', config('app.url') . '/payfast/webhook'),
    'card_update_link_css' => env('CARD_UPDATE_LINK_CSS', 'inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition'),
    'card_updated_return_url' => env('CARD_UPDATED_RETURN_URL', config('app.url') . '/user/profile'),
    'plans' => [
        3 => [
            'name' => 'Monthly R 99',
            'start_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'payfast_frequency' => 3, // 3 = monthly
            'initial_amount' => 5.99, // For card updates or reactivatitions, this should be zero
            'recurring_amount' => 5.99,
        ],
        6 => [
            'name' => 'Yearly R 1089',
            'start_date' => \Carbon\Carbon::now()->format('Y-m-d'),
            'payfast_frequency' => 6, // 6 = yearly
            'initial_amount' => 6.89, // For card updates or reactivatitions, this should be zero
            'recurring_amount' => 6.89,
        ]
    ],
    'cancelation_reasons' => [
        'Too expensive',
        'Lacks features',
        'Not what I expected',
    ],
];
