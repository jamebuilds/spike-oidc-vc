<?php

namespace App\Services\Wallet;

use App\Models\WalletKey;

class WalletKeyService
{
    public function __construct(private SdJwtService $sdJwtService) {}

    /**
     * Generate an EC P-256 key pair and return JWK representations.
     *
     * @return array{publicJwk: array<string, string>, privateJwk: array<string, string>, privatePem: string}
     */
    public function generateKeyPair(): array
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'private_key_bits' => 384,
        ]);

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);

        $x = $this->sdJwtService->base64urlEncode($details['ec']['x']);
        $y = $this->sdJwtService->base64urlEncode($details['ec']['y']);
        $d = $this->sdJwtService->base64urlEncode($details['ec']['d']);

        $publicJwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
        ];

        $privateJwk = [
            ...$publicJwk,
            'd' => $d,
            'pem' => $privatePem,
        ];

        return [
            'publicJwk' => $publicJwk,
            'privateJwk' => $privateJwk,
            'privatePem' => $privatePem,
        ];
    }

    /**
     * Create a Key Binding JWT for a verifiable presentation.
     */
    public function createKeyBindingJwt(WalletKey $key, string $audience, string $nonce, string $sdHash): string
    {
        $header = [
            'alg' => 'ES256',
            'typ' => 'kb+jwt',
        ];

        $payload = [
            'iat' => time(),
            'aud' => $audience,
            'nonce' => $nonce,
            'sd_hash' => $sdHash,
        ];

        // Reconstruct PEM from the stored private JWK
        $privatePem = $this->jwkToPem($key->private_jwk);

        return $this->encodeJwt($header, $payload, $privatePem);
    }

    /**
     * Low-level JWT creation: base64url encode header + payload, sign with ES256.
     *
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    public function encodeJwt(array $header, array $payload, string $privateKeyPem): string
    {
        $headerEncoded = $this->sdJwtService->base64urlEncode(json_encode($header));
        $payloadEncoded = $this->sdJwtService->base64urlEncode(json_encode($payload));

        $signingInput = $headerEncoded.'.'.$payloadEncoded;

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw R||S format (64 bytes for P-256)
        $rawSignature = $this->derToRaw($derSignature);

        $signatureEncoded = $this->sdJwtService->base64urlEncode($rawSignature);

        return $headerEncoded.'.'.$payloadEncoded.'.'.$signatureEncoded;
    }

    /**
     * Extract PEM from stored private JWK (which includes the PEM string).
     *
     * @param  array<string, string>|string  $jwk
     */
    private function jwkToPem(array|string $jwk): string
    {
        if (is_string($jwk)) {
            $jwk = json_decode($jwk, true);
        }

        return $jwk['pem'];
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S format.
     */
    private function derToRaw(string $der): string
    {
        $offset = 2; // skip SEQUENCE tag + length

        // Read R
        $offset++; // INTEGER tag
        $rLength = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        // Read S
        $offset++; // INTEGER tag
        $sLength = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLength);

        // Pad or trim to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }
}
