<?php

namespace App\Enums;

/**
 * Single source of truth for the values stored in `nurses.graduation_type`.
 * Since 2026_11_08_000001 the column is a plain string, so this enum (and the
 * validation rules built from it) is what limits the allowed values.
 *
 * Before this enum existed the same field was validated with two different
 * lists: registration accepted 'مدرسة التمريض والقبالة' while the profile
 * update and the user-facing filter accepted the shorter 'مدرسة'. That
 * mismatch is what made the option get rejected at registration time.
 */
enum GraduationType: string
{
    // Declaration order = display order (registration dropdown, filters).
    case NURSING_COLLEGE        = 'كلية التمريض';
    case HEALTH_SCIENCES_COLLEGE = 'كلية العلوم الصحية';
    case NURSING_SCHOOL         = 'مدرسة التمريض والقبالة';
    case INSTITUTE              = 'معهد طبي/صحي';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

    /**
     * Legacy/short spellings that were used by older clients and by the old
     * inline validation rules. Kept so a value that is already stored (or
     * still sent by an outdated app build) can be mapped instead of being
     * rejected or silently lost.
     *
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return [
            'معهد'                  => self::INSTITUTE->value,
            'معهد طبي'              => self::INSTITUTE->value,
            'مدرسة'                 => self::NURSING_SCHOOL->value,
            'مدرسة تمريض والقبالة'  => self::NURSING_SCHOOL->value,
            'مدرسة التمريض'         => self::NURSING_SCHOOL->value,
            'كلية'                  => self::NURSING_COLLEGE->value,
            // Removed options (Nov 2026): existing rows are migrated to
            // "كلية التمريض" and old app builds sending them still work.
            'جامعة'                 => self::NURSING_COLLEGE->value,
            'ماجستير'               => self::NURSING_COLLEGE->value,
            'دكتوراه'               => self::NURSING_COLLEGE->value,
            'العلوم الصحية'         => self::HEALTH_SCIENCES_COLLEGE->value,
        ];
    }

    /**
     * Resolve any accepted spelling (canonical or legacy) to the canonical
     * value stored in the database.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim($value);

        if (self::tryFrom($value)) {
            return $value;
        }

        return self::aliases()[$value] ?? null;
    }
}
