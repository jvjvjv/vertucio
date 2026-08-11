<?php

namespace App\Models;

use App\Concerns\RendersResultLinks;
use App\Search\Enums\SponsorshipStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SponsoredResultFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hand-authored promo rendered in the SERP's sponsored slot.
 *
 * Unrelated to the snapshot pipeline: never fetched, never expired by a
 * fetch. Unguarded because it is written only by the sponsored:* commands,
 * never from request input.
 *
 * @property int $id
 * @property int $position
 * @property string $url
 * @property string $title
 * @property string|null $description
 * @property string|null $image_url
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Guarded([])]
class SponsoredResult extends Model
{
    /** @use HasFactory<SponsoredResultFactory> */
    use HasFactory, RendersResultLinks;

    /**
     * Rows whose window is open at the given moment.
     *
     * The window is half-open: inclusive of starts_at, exclusive of ends_at.
     * A null bound is unbounded on that side.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function active(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query
            ->where(fn (Builder $query) => $query
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $at));
    }

    /**
     * Optional title/description filter, applied alongside active().
     *
     * A null or blank term is a no-op — callers gate this with
     * `when(filled($term), ...)` rather than always calling it, but the
     * no-op behavior here makes that gate a convenience, not a requirement.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(
            fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
        );
    }

    /**
     * Where this promo sits relative to its window.
     *
     * Expired wins over Scheduled: a closed window is closed regardless of
     * when it opened.
     */
    public function status(?CarbonImmutable $at = null): SponsorshipStatus
    {
        $at ??= CarbonImmutable::now();

        if ($this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($at)) {
            return SponsorshipStatus::Expired;
        }

        if ($this->starts_at !== null && $this->starts_at->greaterThan($at)) {
            return SponsorshipStatus::Scheduled;
        }

        return SponsorshipStatus::Active;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
