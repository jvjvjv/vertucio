<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SponsorshipInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead submitted through the public "Sponsor Us" form.
 *
 * Fillable, not guarded, because this is the one model in the application
 * written directly from public request input — the explicit allow-list is
 * the mass-assignment guard, unlike SearchResult/SponsoredResult/
 * SearchSnapshot, which are unguarded because nothing but internal code
 * ever writes them.
 *
 * @property int $id
 * @property string $full_name
 * @property string $email
 * @property string $company_name
 * @property string $monthly_budget
 * @property string $message
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['full_name', 'email', 'company_name', 'monthly_budget', 'message'])]
class SponsorshipInquiry extends Model
{
    /** @use HasFactory<SponsorshipInquiryFactory> */
    use HasFactory;
}
