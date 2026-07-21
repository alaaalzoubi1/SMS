<?php

namespace App\Enums;

/**
 * The "standard" keys the landing page (resources/views/landing) knows how
 * to render. This is a guide for the admin/seeder, not a hard whitelist —
 * SiteContentController still accepts any string key so new sections can be
 * added without a code change. Add a matching partial under
 * resources/views/landing/partials to actually render a new key.
 */
enum SiteContentKey: string
{
    case THEME = 'theme';
    case HERO = 'hero';
    case HOW_WE_ARE = 'how_we_are';
    case FEATURES = 'features';
    case STATS = 'stats';
    case SCREENSHOTS = 'screenshots';
    case CTA = 'cta';
    case FOOTER = 'footer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
