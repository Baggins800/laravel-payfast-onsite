<?php

namespace FintechSystems\Payfast;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'recurring_amount', 'initial_amount', 'payfast_frequency'];
}

