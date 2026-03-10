<?php

namespace App\Services\Wallet;

class SdJwtService
{
    /**
     * Parse a raw SD-JWT string into its components.
     *
     * @return array{issuerJwt: string, disclosures: list<string>, keyBindingJwt: string|null}
     */
    public function parse(string $rawSdJwt): array
    {
        $parts = explode('~', $rawSdJwt);

        $issuerJwt = array_shift($parts);

        // Last element after final ~ is either empty string (trailing ~) or a KB-JWT
        $lastPart = array_pop($parts);
        $keyBindingJwt = (! empty($lastPart) && substr_count($lastPart, '.') === 2)
            ? $lastPart
            : null;

        // If last part was not a KB-JWT but not empty, it's a disclosure
        if ($keyBindingJwt === null && ! empty($lastPart)) {
            $parts[] = $lastPart;
        }

        // Filter out empty strings from disclosures
        $disclosures = array_values(array_filter($parts, fn (string $d): bool => $d !== ''));

        return [
            'issuerJwt' => $issuerJwt,
            'disclosures' => $disclosures,
            'keyBindingJwt' => $keyBindingJwt,
        ];
    }

    /**
     * Decode a JWT payload (no signature verification - spike only).
     *
     * @return array<string, mixed>
     */
    public function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) < 2) {
            return [];
        }

        $payload = $this->base64urlDecode($parts[1]);

        return json_decode($payload, true) ?? [];
    }

    /**
     * Decode a disclosure from base64url to [salt, claimName, claimValue].
     *
     * @return array{0: string, 1: string, 2: mixed}
     */
    public function decodeDisclosure(string $encoded): array
    {
        $json = $this->base64urlDecode($encoded);

        return json_decode($json, true) ?? [];
    }

    /**
     * Compute the SHA-256 digest of a disclosure (base64url-encoded).
     */
    public function computeDisclosureDigest(string $encoded): string
    {
        $hash = hash('sha256', $encoded, true);

        return $this->base64urlEncode($hash);
    }

    /**
     * Build the full disclosure mapping for a raw SD-JWT.
     *
     * @return list<array{salt: string, claimName: string, claimValue: mixed, digest: string, encoded: string}>
     */
    public function buildDisclosureMapping(string $rawSdJwt): array
    {
        $parsed = $this->parse($rawSdJwt);
        $mapping = [];

        foreach ($parsed['disclosures'] as $encoded) {
            $decoded = $this->decodeDisclosure($encoded);

            if (count($decoded) < 3) {
                continue;
            }

            $mapping[] = [
                'salt' => $decoded[0],
                'claimName' => $decoded[1],
                'claimValue' => $decoded[2],
                'digest' => $this->computeDisclosureDigest($encoded),
                'encoded' => $encoded,
            ];
        }

        return $mapping;
    }

    /**
     * Reassemble a presentation SD-JWT from components.
     */
    public function buildPresentationSdJwt(string $issuerJwt, array $selectedDisclosures, string $kbJwt): string
    {
        $parts = [$issuerJwt];

        foreach ($selectedDisclosures as $disclosure) {
            $parts[] = $disclosure;
        }

        return implode('~', $parts).'~'.$kbJwt;
    }

    /**
     * Compute the sd_hash of an SD-JWT presentation (without KB-JWT).
     * The input is: <issuer-jwt>~<disc1>~<disc2>~...~
     */
    public function computeSdHash(string $presentationWithoutKb): string
    {
        $hash = hash('sha256', $presentationWithoutKb, true);

        return $this->base64urlEncode($hash);
    }

    public function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64urlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');

        return base64_decode(strtr($padded, '-_', '+/'));
    }
}
