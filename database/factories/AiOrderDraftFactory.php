<?php

namespace Database\Factories;

use App\Enums\AiOrderDraftStatus;
use App\Enums\CustomerSource;
use App\Models\AiOrderDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiOrderDraft>
 */
class AiOrderDraftFactory extends Factory
{
    protected $model = AiOrderDraft::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'raw_message' => 'Boss pa order po 10 pcs reversible black white regular need sa Friday',
            'parsed_json' => [
                'intent' => 'create_order',
                'customer_name' => null,
                'customer_contact' => null,
                'customer_source' => 'facebook',
                'items' => [
                    [
                        'product_name' => 'Reversible Adult',
                        'color_name' => 'BLACK / WHITE',
                        'size_name' => 'Regular',
                        'quantity' => 10,
                        'notes' => null,
                    ],
                ],
                'deadline' => 'Friday',
                'notes' => 'Need sa Friday',
                'missing_fields' => [],
                'confidence' => 0.9,
            ],
            'matched_json' => null,
            'confidence_score' => 0.9,
            'status' => AiOrderDraftStatus::Draft,
            'customer_name' => null,
            'customer_contact' => null,
            'customer_source' => CustomerSource::Facebook,
            'customer_notes' => 'Need sa Friday',
            'customer_order_id' => null,
            'created_by' => User::factory(),
        ];
    }
}
