<?php

namespace FintechSystems\Payfast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Plan extends Model
{
    protected $fillable = ['name', 'recurring_amount', 'initial_amount', 'payfast_frequency'];

    public function getTable()
    {
        return Config::get('payfast.tables.plans');
    }
}

