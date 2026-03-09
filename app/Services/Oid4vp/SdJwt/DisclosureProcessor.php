<?php

namespace App\Services\Oid4vp\SdJwt;

class DisclosureProcessor
{
    /**
     * Split an SD-JWT compact serialization into its parts.
     *
     * Format: issuer_jwt~disclosure1~disclosure2~...~kb_jwt
     * The last part after the final ~ is the KB-JWT (if present).
     *
     * @return array{issuer_jwt: string, disclosures: array<int, string>, kb_jwt: ?string}
     */
    public function split(string $sdJwtCompact): array
    {
        $parts = explode('~', $sdJwtCompact);

        $issuerJwt = array_shift($parts);

        // If the last element is empty (trailing ~), there's no KB-JWT
        $kbJwt = null;
        if (count($parts) > 0) {
            $last = array_pop($parts);
            if ($last !== '') {
                $kbJwt = $last;
            }
        }

        // Remaining parts are disclosures (filter out empty strings)
        $disclosures = array_values(array_filter($parts, fn (string $d): bool => $d !== ''));

        return [
            'issuer_jwt' => $issuerJwt,
            'disclosures' => $disclosures,
            'kb_jwt' => $kbJwt,
        ];
    }

    /**
     * Decode a base64url-encoded disclosure into [salt, name, value].
     *
     * @return array{0: string, 1: string, 2: mixed}
     */
    public function decodeDisclosure(string $disclosure): array
    {
        $json = JwtParser::base64urlDecode($disclosure);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || count($decoded) < 3) {
            throw new \InvalidArgumentException('Invalid disclosure: expected JSON array with at least 3 elements');
        }

        return [$decoded[0], $decoded[1], $decoded[2]];
    }

    /**
     * Hash a disclosure using SHA-256 and base64url-encode the result.
     */
    public function hashDisclosure(string $disclosure): string
    {
        return JwtParser::base64urlEncode(hash('sha256', $disclosure, true));
    }

    /**
     * Match disclosures against the _sd array in the JWT payload.
     *
     * @param  array<int, string>  $sdArray  The _sd array from the JWT payload
     * @param  array<int, string>  $disclosures  The raw disclosure strings
     * @return array<string, mixed> Map of claim name => claim value for matched disclosures
     */
    public function matchDisclosures(array $sdArray, array $disclosures): array
    {
        $matched = [];
        $sdHashes = array_flip($sdArray);

        foreach ($disclosures as $disclosure) {
            $hash = $this->hashDisclosure($disclosure);

            if (isset($sdHashes[$hash])) {
                [$salt, $name, $value] = $this->decodeDisclosure($disclosure);
                $matched[$name] = $value;
            }
        }

        return $matched;
    }

    /**
     * Reconstruct the full claim set by merging issuer claims with disclosed claims.
     *
     * @param  array<string, mixed>  $payload  The issuer JWT payload
     * @param  array<string, mixed>  $disclosedClaims  Matched disclosed claims
     * @return array<string, mixed>
     */
    public function reconstructClaims(array $payload, array $disclosedClaims): array
    {
        $claims = $payload;

        // Remove SD-JWT specific fields
        unset($claims['_sd'], $claims['_sd_alg']);

        // Merge disclosed claims
        return array_merge($claims, $disclosedClaims);
    }
}
