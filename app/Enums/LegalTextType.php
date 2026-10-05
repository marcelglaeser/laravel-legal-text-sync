<?php

namespace App\Enums;

enum LegalTextType: string
{
    case Imprint = 'imprint';
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Imprint => 'Impressum',
            self::Terms => 'AGB',
            self::Privacy => 'Datenschutzerklärung',
            self::Withdrawal => 'Widerrufsbelehrung',
        };
    }
}
