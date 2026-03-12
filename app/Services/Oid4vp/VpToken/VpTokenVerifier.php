<?php

namespace App\Services\Oid4vp\VpToken;

use App\Services\Oid4vp\SdJwt\JwtParser;
use App\Services\Oid4vp\SdJwt\VerificationResult;

class VpTokenVerifier
{
    public function __construct(
        private JwtParser $jwtParser,
    ) {}

    public function verify(string $vpToken, string $expectedNonce): VerificationResult
    {
        $errors = [];
        $claims = [];
        $disclosedClaims = [];
        $vct = null;
        $nonce = null;

        try {
            // 1. Parse the outer VP JWT
            $vpJwt = $this->jwtParser->parse($vpToken);
            $vpPayload = $vpJwt['payload'];

            // 2. Validate nonce
            $nonce = $vpPayload['nonce'] ?? null;
            if ($nonce !== $expectedNonce) {
                $errors[] = "Nonce mismatch: expected '{$expectedNonce}', got '{$nonce}'";
            }

            // 3. Extract verifiable credentials from the VP
            $vp = $vpPayload['vp'] ?? [];
            $verifiableCredentials = $vp['verifiableCredential'] ?? [];

            if (empty($verifiableCredentials)) {
                $errors[] = 'No verifiable credentials found in VP token';
            }

            // 4. Parse each VC and extract claims
            foreach ($verifiableCredentials as $vcToken) {
                if (! is_string($vcToken)) {
                    continue;
                }

                $vcJwt = $this->jwtParser->parse($vcToken);
                $vcPayload = $vcJwt['payload'];

                // W3C VC JWT: claims are under "vc" key
                $vc = $vcPayload['vc'] ?? $vcPayload;

                $types = $vc['type'] ?? [];
                $vct = collect($types)
                    ->first(fn (string $t): bool => $t !== 'VerifiableCredential');

                $subject = $vc['credentialSubject'] ?? [];
                $disclosedClaims = array_merge($disclosedClaims, $subject);
                $claims = array_merge($claims, $vc);
            }
        } catch (\Throwable $e) {
            $errors[] = 'Verification error: '.$e->getMessage();
        }

        return new VerificationResult(
            isValid: count($errors) === 0,
            claims: $claims,
            disclosedClaims: $disclosedClaims,
            vct: $vct,
            nonce: $nonce,
            errors: $errors,
        );
    }
}
