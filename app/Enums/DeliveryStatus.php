<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Delivered => 'green',
            self::Failed => 'red',
        };
    }
}
