<?php

namespace Database\Factories;

use App\Models\SearchSnapshot;
use App\Search\Enums\SearchVertical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchSnapshot>
 */
class SearchSnapshotFactory extends Factory
{
    /**
     * @var class-string<SearchSnapshot>
     */
    protected $model = SearchSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'brave',
            'vertical' => SearchVertical::Web,
            'query' => 'vertucio',
            'fetched_at' => now(),
            'result_count' => 0,
            'meta' => [],
        ];
    }
}
