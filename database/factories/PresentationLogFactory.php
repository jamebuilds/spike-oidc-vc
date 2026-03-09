<?php

namespace Database\Factories;

use App\Models\Credential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PresentationLog>
 */
class PresentationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'credential_id' => Credential::factory(),
            'verifier_client_id' => fake()->url(),
            'nonce' => fake()->uuid(),
            'disclosed_claims' => ['given_name', 'family_name'],
            'response_uri' => fake()->url(),
            'status' => 'success',
            'submitted_at' => now(),
        ];
    }
}
