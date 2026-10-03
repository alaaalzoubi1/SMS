<?php

namespace App\Enums;

/**
 * Single source of truth for the values stored in
 * `nurses.graduation_type`. These values MUST stay in sync with the enum
 * definition in the `create_nurses_table` migration — the database column is
 * a native enum, so anything outside this list is rejected on insert.
 *
 * Before this enum existed the same field was validated with two different
 * lists: registration accepted 'مدرسة التمريض والقبالة' while the profile
 * update and the user-facing filter accepted the shorter 'مدرسة'. That
 * mismatch is what made the option get rejected at registration time.
 */
enum GraduationType: string
{
    case INSTITUTE      = 'معهد طبي/صحي';
    case NURSING_SCHOOL = 'مدرسة التمريض والقبالة';
    case UNIVERSITY     = 'جامعة';
    case MASTER         = 'ماجستير';
    case DOCTORATE      = 'دكتوراه';

    public function label(): string
    {
        return match ($this) {
            self::INSTITUTE      => 'معهد طبي/صحي',
            self::NURSING_SCHOOL => 'مدرسة التمريض والقبالة',
            self::UNIVERSITY     => 'جامعة',
            self::MASTER         => 'ماجستير',
            self::DOCTORATE      => 'دكتوراه',
        };
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
            'معهد'      => self::INSTITUTE->value,
            'معهد طبي'  => self::INSTITUTE->value,
            'مدرسة'     => self::NURSING_SCHOOL->value,
            'كلية'      => self::UNIVERSITY->value,
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
