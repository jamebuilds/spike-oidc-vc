<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WalletKey>
 */
class WalletKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'algorithm' => 'ES256',
            'public_jwk' => ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'stub', 'y' => 'stub'],
            'private_jwk' => json_encode(['kty' => 'EC', 'crv' => 'P-256', 'x' => 'stub', 'y' => 'stub', 'd' => 'stub']),
        ];
    }
}
