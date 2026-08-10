<?php

namespace App\Models;

use App\Search\Enums\SearchVertical;
use Database\Factories\SearchSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
