<?php

namespace FintechSystems\Payfast;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name', 'recurring_amount', 'initial_amount', 'payfast_frequency'];
    protected $table =config('payfast.tables.plans'); 
}

