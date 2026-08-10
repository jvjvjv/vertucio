<?php

namespace Database\Factories;

use App\Models\SponsorshipInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SponsorshipInquiry>
 */
class SponsorshipInquiryFactory extends Factory
{
    /**
     * @var class-string<SponsorshipInquiry>
     */
    protected $model = SponsorshipInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company_name' => fake()->company(),
            'monthly_budget' => fake()->randomElement(['under_1000', '1000_5000', '5000_10000', '10000_plus']),
            'message' => fake()->paragraph(),
        ];
    }
}
