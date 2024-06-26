<?php

namespace FintechSystems\Payfast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Money\Currency;


class Receipt extends Model
{
    public function getTable() {
      return Config::get('payfast.tables.receipts');
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
        'quantity' => 'integer',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the billable model related to the receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function billable()
    {
        return $this->morphTo();
    }

    /**
     * Get the subscription related to the receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Cashier::$subscriptionModel, 'payfast_token', 'token');
    }

    /**
     * Get the total amount that was paid.
     *
     * @return string
     */
    public function amount()
    {
        return $this->formatAmount((int) ($this->amount * 100));
    }

    /**
     * Get the total tax that was paid.
     *
     * @return string
     */
    public function tax()
    {
        return $this->formatAmount((int) ($this->tax * 100));
    }

    /**
     * Get the used currency for the receipt.
     *
     * @return \Money\Currency
     */
    public function currency(): Currency
    {
        return new Currency($this->currency);
    }

    /**
     * Format the given amount into a displayable currency.
     *
     * @param  int  $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return Cashier::formatAmount($amount, $this->currency);
    }
}
