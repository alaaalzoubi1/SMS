<?php

namespace App\Enums;

enum SiteContentType: string
{
    case SECTION = 'section';
    case SETTING = 'setting';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
