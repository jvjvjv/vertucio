<?php

namespace Database\Factories;

use App\Models\SponsoredResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsoredResult>
 */
class SponsoredResultFactory extends Factory
{
    /**
     * @var class-string<SponsoredResult>
     */
    protected $model = SponsoredResult::class;

    /**
     * An unbounded promo — live from creation, never ending.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => 1,
            'url' => 'https://vertucio.com/work',
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'image_url' => null,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * A promo with an explicitly open window on both sides.
     */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
    }

    /**
     * A promo whose window has not opened yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    /**
     * A promo whose window has closed.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
        ]);
    }
}
