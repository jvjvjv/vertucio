<?php

namespace App\Models;

use App\Search\Enums\SearchVertical;
use Carbon\CarbonImmutable;
use Database\Factories\SearchSnapshotFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $provider
 * @property SearchVertical $vertical
 * @property string $query
 * @property CarbonImmutable $fetched_at
 * @property int $result_count
 * @property array<string, mixed>|null $meta
 * @property-read Collection<int, SearchResult> $results
 */
class SearchSnapshot extends Model
{
    /** @use HasFactory<SearchSnapshotFactory> */
    use HasFactory;

    /**
     * Written only by StoreSearchSnapshot, never from request input.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return HasMany<SearchResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vertical' => SearchVertical::class,
            'fetched_at' => 'datetime',
            'result_count' => 'integer',
            'meta' => 'array',
        ];
    }
}
