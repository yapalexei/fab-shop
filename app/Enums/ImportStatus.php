<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
