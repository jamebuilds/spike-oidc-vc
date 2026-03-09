<?php

namespace App\Services\Oid4vp\SdJwt;

class VerificationResult
{
    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $disclosedClaims
     * @param  array<int, string>  $errors
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $claims = [],
        public readonly array $disclosedClaims = [],
        public readonly ?string $vct = null,
        public readonly ?string $nonce = null,
        public readonly array $errors = [],
    ) {}

    /**
     * @return array{is_valid: bool, claims: array<string, mixed>, disclosed_claims: array<string, mixed>, vct: ?string, nonce: ?string, errors: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'claims' => $this->claims,
            'disclosed_claims' => $this->disclosedClaims,
            'vct' => $this->vct,
            'nonce' => $this->nonce,
            'errors' => $this->errors,
        ];
    }
}
