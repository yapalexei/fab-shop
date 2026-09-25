<?php

namespace App\Enums;

/**
 * The three fixed roles a shop's job_stages can carry - everything else in
 * between is the shop's own custom, ordered, freely-named stages (see
 * App\Support\JobStages). A stage with role null is just a regular custom
 * stage; these three are what the business logic that used to be hardcoded
 * to specific JobStatus cases (Shipped/Invoiced) now keys off instead.
 */
enum JobStageRole: string
{
    case NotStarted = 'not_started';
    case Shipped = 'shipped';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Shipped => 'Shipped',
            self::Invoiced => 'Invoiced',
        };
    }
}
