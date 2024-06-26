<?php

namespace FintechSystems\Payfast;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;

/**
 * @property \FintechSystems\Payfast\Billable $billable
 */
class Customer extends Model
{    
    public function getTable() {
        return Config::get('payfast.tables.customers');
    }

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the billable model related to the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Determine if the PayFast model is on a "generic" trial at the model level.
     *
     * @return bool
     */
    public function onGenericTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
}
