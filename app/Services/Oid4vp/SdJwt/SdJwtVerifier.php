<?php

namespace App\Services\Oid4vp\SdJwt;

class SdJwtVerifier
{
    public function __construct(
        private JwtParser $jwtParser,
        private DisclosureProcessor $disclosureProcessor,
    ) {}

    public function verify(string $vpToken, string $expectedNonce): VerificationResult
    {
        $errors = [];
        $claims = [];
        $disclosedClaims = [];
        $vct = null;
        $nonce = null;

        try {
            // 1. Split SD-JWT into parts
            $parts = $this->disclosureProcessor->split($vpToken);

            // 2. Parse issuer JWT
            $issuerJwt = $this->jwtParser->parse($parts['issuer_jwt']);
            $payload = $issuerJwt['payload'];
            $header = $issuerJwt['header'];

            // 3. Verify ES256 signature (if not skipped)
            if (! config('oid4vp.skip_signature_verification')) {
                $issuer = $payload['iss'] ?? '';
                $publicKeyPem = $this->resolveIssuerPublicKey($issuer);

                if (empty($publicKeyPem)) {
                    $errors[] = 'Could not resolve issuer public key from iss: '.$issuer;
                } else {
                    $verified = $this->jwtParser->verifySignature(
                        $issuerJwt['signed_input'],
                        $issuerJwt['signature'],
                        $publicKeyPem
                    );

                    if (! $verified) {
                        $errors[] = 'Issuer JWT signature verification failed';
                    }
                }
            }

            // 4. Validate standard claims
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $errors[] = 'Token has expired (exp: '.date('Y-m-d H:i:s', $payload['exp']).')';
            }

            if (isset($payload['iat']) && $payload['iat'] > time() + 300) {
                $errors[] = 'Token issued in the future (iat: '.date('Y-m-d H:i:s', $payload['iat']).')';
            }

            $vct = $payload['vct'] ?? null;

            // 5. Match disclosures against _sd hashes
            $sdArray = $payload['_sd'] ?? [];
            if (count($parts['disclosures']) > 0 && count($sdArray) > 0) {
                $disclosedClaims = $this->disclosureProcessor->matchDisclosures(
                    $sdArray,
                    $parts['disclosures']
                );
            } elseif (count($parts['disclosures']) > 0 && count($sdArray) === 0) {
                $errors[] = 'Disclosures present but no _sd array in issuer JWT';
            }

            // 6. Reconstruct full claim set
            $claims = $this->disclosureProcessor->reconstructClaims($payload, $disclosedClaims);

            // 7. Verify nonce from KB-JWT
            if ($parts['kb_jwt'] !== null) {
                $kbJwt = $this->jwtParser->parse($parts['kb_jwt']);
                $nonce = $kbJwt['payload']['nonce'] ?? null;

                if ($nonce !== $expectedNonce) {
                    $errors[] = "Nonce mismatch: expected '{$expectedNonce}', got '{$nonce}'";
                }
            } else {
                $errors[] = 'No Key Binding JWT present';
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

    /**
     * Resolve the issuer's public key from the iss claim.
     *
     * For did:jwk: issuers, the JWK is embedded in the DID itself.
     * Falls back to the static config key for other issuer types.
     */
    private function resolveIssuerPublicKey(string $issuer): string
    {
        if (! str_starts_with($issuer, 'did:jwk:')) {
            throw new \InvalidArgumentException('Unsupported issuer DID method: '.$issuer);
        }

        $jwkBase64url = substr($issuer, strlen('did:jwk:'));
        $jwkJson = JwtParser::base64urlDecode($jwkBase64url);
        $jwk = json_decode($jwkJson, true);

        if (! is_array($jwk)) {
            throw new \InvalidArgumentException('Failed to decode JWK from issuer DID: '.$issuer);
        }

        return $this->jwtParser->jwkToPem($jwk);
    }
}
