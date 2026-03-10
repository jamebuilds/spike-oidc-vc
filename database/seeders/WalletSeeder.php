<?php

namespace Database\Seeders;

use App\Models\Credential;
use App\Models\User;
use App\Models\WalletKey;
use App\Services\Wallet\SdJwtService;
use App\Services\Wallet\WalletKeyService;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $sdJwtService = app(SdJwtService::class);
        $walletKeyService = app(WalletKeyService::class);

        // Get or create the test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );

        // Generate wallet key pair
        $keyPair = $walletKeyService->generateKeyPair();

        WalletKey::updateOrCreate(
            ['user_id' => $user->id],
            [
                'algorithm' => 'ES256',
                'public_jwk' => $keyPair['publicJwk'],
                'private_jwk' => json_encode($keyPair['privateJwk']),
            ],
        );

        // Generate a test issuer key for signing SD-JWT VCs
        $issuerKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);
        openssl_pkey_export($issuerKey, $issuerPrivatePem);

        // Seed IdentityCredential
        $this->seedCredential(
            $sdJwtService,
            $walletKeyService,
            $user,
            $keyPair,
            $issuerPrivatePem,
            type: 'IdentityCredential',
            issuer: 'https://issuer.example.com',
            claims: [
                ['given_name', 'John'],
                ['family_name', 'Doe'],
                ['date_of_birth', '1990-01-15'],
                ['address', [
                    'street_address' => '123 Main St',
                    'locality' => 'Anytown',
                    'region' => 'CA',
                    'country' => 'US',
                ]],
                ['email', 'john.doe@example.com'],
                ['phone_number', '+1-555-0123'],
            ],
        );

        // Seed BoardingPass
        $this->seedCredential(
            $sdJwtService,
            $walletKeyService,
            $user,
            $keyPair,
            $issuerPrivatePem,
            type: 'BoardingPass',
            issuer: 'https://airline.example.com',
            claims: [
                ['passenger_name', 'John Doe'],
                ['flight_number', 'QF42'],
                ['departure', 'SYD'],
                ['arrival', 'MEL'],
                ['departure_date', '2026-04-15'],
                ['seat', '14A'],
                ['boarding_group', 'B'],
                ['class', 'Economy'],
            ],
        );

        $this->command->info('Wallet seeder completed:');
        $this->command->info("  - User: {$user->email}");
        $this->command->info('  - Wallet key: ES256 generated');
        $this->command->info('  - Credentials: IdentityCredential, BoardingPass');
    }

    /**
     * @param  array{publicJwk: array<string, string>, privateJwk: array<string, string>, privatePem: string}  $keyPair
     * @param  list<array{0: string, 1: mixed}>  $claims
     */
    private function seedCredential(
        SdJwtService $sdJwtService,
        WalletKeyService $walletKeyService,
        User $user,
        array $keyPair,
        string $issuerPrivatePem,
        string $type,
        string $issuer,
        array $claims,
    ): void {
        $disclosures = [];
        $sdDigests = [];

        foreach ($claims as [$claimName, $claimValue]) {
            $salt = $sdJwtService->base64urlEncode(random_bytes(16));
            $disclosureArray = [$salt, $claimName, $claimValue];
            $encoded = $sdJwtService->base64urlEncode(json_encode($disclosureArray));
            $digest = $sdJwtService->computeDisclosureDigest($encoded);

            $disclosures[] = $encoded;
            $sdDigests[] = $digest;
        }

        $now = time();
        $payload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + (365 * 24 * 60 * 60),
            'vct' => $type,
            'cnf' => [
                'jwk' => $keyPair['publicJwk'],
            ],
            '_sd' => $sdDigests,
            '_sd_alg' => 'sha-256',
        ];

        $header = [
            'alg' => 'ES256',
            'typ' => 'vc+sd-jwt',
        ];

        $issuerJwt = $walletKeyService->encodeJwt($header, $payload, $issuerPrivatePem);
        $rawSdJwt = $issuerJwt.'~'.implode('~', $disclosures).'~';
        $disclosureMapping = $sdJwtService->buildDisclosureMapping($rawSdJwt);

        Credential::updateOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            [
                'issuer' => $issuer,
                'raw_sd_jwt' => $rawSdJwt,
                'payload_claims' => $payload,
                'disclosure_mapping' => $disclosureMapping,
                'cnf_jwk' => $keyPair['publicJwk'],
                'issued_at' => now(),
                'expires_at' => now()->addYear(),
            ],
        );
    }
}
