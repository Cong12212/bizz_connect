<?php

namespace App\Enums;

enum Plan: string
{
    case Free     = 'free';
    case Pro      = 'pro';
    case ProPlus  = 'pro_plus';

    public function isPaid(): bool
    {
        return match($this) {
            self::Free => false,
            default    => true,
        };
    }
}
