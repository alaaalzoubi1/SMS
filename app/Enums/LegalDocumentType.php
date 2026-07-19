<?php

namespace App\Enums;

enum LegalDocumentType: string
{
    case PRIVACY_POLICY = 'privacy_policy';
    case TERMS_AND_CONDITIONS = 'terms_and_conditions';

    public function label(): string
    {
        return match ($this) {
            self::PRIVACY_POLICY => 'Privacy Policy',
            self::TERMS_AND_CONDITIONS => 'Terms and Conditions',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
