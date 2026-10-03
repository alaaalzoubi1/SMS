<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorWorkSchedule extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Canonical day names. Everything that resolves a schedule for a date
     * goes through self::dayKey() so the comparison is never a raw string
     * match against whatever casing the row happens to hold.
     *
     * @var array<int, string>
     */
    public const DAYS = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    protected $fillable = [
        'doctor_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    public function doctor():BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Normalized day key for a given date, e.g. 'friday'.
     */
    public static function dayKey(string|\DateTimeInterface $date): string
    {
        return strtolower(\Carbon\Carbon::parse($date)->format('l'));
    }

    /**
     * The schedule covering the given date, or null when the doctor does not
     * work that day. Comparison is case-insensitive and ignores soft-deleted
     * rows.
     */
    public static function findForDate(int $doctorId, string|\DateTimeInterface $date): ?self
    {
        return static::query()
            ->where('doctor_id', $doctorId)
            ->whereRaw('LOWER(day_of_week) = ?', [self::dayKey($date)])
            ->first();
    }

    /**
     * Working window for a date as [start, end] Carbon instances.
     *
     * A window whose end is not after its start is treated as crossing
     * midnight (e.g. 22:00 → 02:00) and the end is rolled into the next day,
     * so the slot search cannot silently produce a negative range.
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}|null
     */
    public function windowFor(string|\DateTimeInterface $date): ?array
    {
        $day = \Carbon\Carbon::parse($date)->startOfDay();

        $start = $day->copy()->setTimeFromTimeString($this->start_time);
        $end   = $day->copy()->setTimeFromTimeString($this->end_time);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}

