<?php

namespace Database\Factories;

use App\Models\SearchResult;
use App\Models\SearchSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchResult>
 */
class SearchResultFactory extends Factory
{
    /**
     * @var class-string<SearchResult>
     */
    protected $model = SearchResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->url();

        return [
            'search_snapshot_id' => SearchSnapshot::factory(),
            'position' => fake()->numberBetween(1, 20),
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'source' => fake()->domainName(),
            'published_at' => fake()->dateTimeThisYear(),
            'image_url' => null,
            'meta' => [],
        ];
    }
}
