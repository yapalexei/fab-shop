<?php

namespace App\Enums;

enum JobStatus: string
{
    case NotStarted = 'not_started';
    case Cutting = 'cutting';
    case Fitting = 'fitting';
    case Welding = 'welding';
    case Finishing = 'finishing';
    case Shipped = 'shipped';
    case Invoiced = 'invoiced';
}
