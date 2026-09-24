<?php

namespace App\Enums;

enum LineItemType: string
{
    case Material = 'material';
    case Labor = 'labor';
    case Equipment = 'equipment';
    case Tooling = 'tooling';
}
