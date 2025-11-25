<?php
namespace App;

use App\Models\Rating;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Rateable
{
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function getAverageRatingAttribute(): float
    {
        if (isset($this->avg_rating) && $this->avg_rating > 0) {
            return (float) $this->avg_rating;
        }

        return (float) round($this->ratings()->avg('rating') ?? 0, 2);
    }


    public function ratingsCount(): int
    {
        if (isset($this->ratings_count)) {
            return (int) $this->ratings_count;
        }
        return $this->ratings()->count();
    }

    public function hasRatedBy($userId): bool
    {
        return $this->ratings()->where('user_id', $userId)->exists();
    }

    public function addOrUpdateRating($userId, int $score, ?string $review = null,)
    {
        $rating = $this->ratings()->updateOrCreate(
            ['user_id' => $userId],
            ['rating' => $score, 'review' => $review]
        );

        if (isset($this->avg_rating) || isset($this->ratings_count)) {
            $this->refreshAggregates();
        }

        return $rating;
    }

    public function refreshAggregates()
    {
        $avg = $this->ratings()->avg('rating') ?? 0;
        $count = $this->ratings()->count();

        $this->avg_rating = round($avg, 2);
        $this->ratings_count = $count;
        $this->saveQuietly();
    }
}
