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
    case Cancelled = 'cancelled';

    /**
     * Position on the traveler ladder - lower is earlier. Cancelled has no
     * position; it's a side exit, not a rung.
     */
    public function order(): ?int
    {
        return match ($this) {
            self::NotStarted => 0,
            self::Cutting => 1,
            self::Fitting => 2,
            self::Welding => 3,
            self::Finishing => 4,
            self::Shipped => 5,
            self::Invoiced => 6,
            self::Cancelled => null,
        };
    }

    /**
     * Once a job reaches Finishing it can't be walked back - painted/coated
     * work can't safely return to Welding. Below that, while it's still "in
     * hand" on the shop floor, a mistake can be corrected by moving back to
     * any earlier in-shop state.
     */
    public function isInShop(): bool
    {
        return $this->order() !== null && $this->order() <= self::Welding->order();
    }

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Cutting => 'Cutting',
            self::Fitting => 'Fitting',
            self::Welding => 'Welding',
            self::Finishing => 'Finishing',
            self::Shipped => 'Shipped',
            self::Invoiced => 'Invoiced',
            self::Cancelled => 'Cancelled',
        };
    }
}
