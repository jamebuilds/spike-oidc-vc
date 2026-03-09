<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issuer' => fake()->url(),
            'type' => 'IdentityCredential',
            'raw_sd_jwt' => 'eyJ0eXAiOiJ2YytzZC1qd3QiLCJhbGciOiJFUzI1NiJ9.eyJfc2QiOltdLCJ2Y3QiOiJJZGVudGl0eUNyZWRlbnRpYWwifQ.stub~',
            'payload_claims' => ['vct' => 'IdentityCredential'],
            'disclosure_mapping' => [],
            'cnf_jwk' => null,
            'issued_at' => now(),
            'expires_at' => now()->addYear(),
        ];
    }
}
