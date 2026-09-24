<?php

namespace App\Enums;

enum BillingMethod: string
{
    case Hourly = 'hourly';
    case Flat = 'flat';
}
