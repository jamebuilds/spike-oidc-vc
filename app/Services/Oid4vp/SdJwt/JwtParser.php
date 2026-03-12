<?php

namespace App\Services\Oid4vp\SdJwt;

class JwtParser
{
    /**
     * Parse a compact JWS (header.payload.signature) into its components.
     *
     * @return array{header: array<string, mixed>, payload: array<string, mixed>, signature: string, signed_input: string}
     */
    public function parse(string $compactJws): array
    {
        $parts = explode('.', $compactJws);

        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid JWS: expected 3 dot-separated parts, got '.count($parts));
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(self::base64urlDecode($headerB64), true);
        $payload = json_decode(self::base64urlDecode($payloadB64), true);
        $signature = self::base64urlDecode($signatureB64);

        if (! is_array($header) || ! is_array($payload)) {
            throw new \InvalidArgumentException('Invalid JWS: failed to decode header or payload as JSON');
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'signature' => $signature,
            'signed_input' => $headerB64.'.'.$payloadB64,
        ];
    }

    /**
     * Verify an ES256 signature against a PEM public key.
     */
    public function verifySignature(string $signedInput, string $signature, string $publicKeyPem): bool
    {
        $derSignature = $this->rawToDer($signature);

        $publicKey = openssl_pkey_get_public($publicKeyPem);

        if ($publicKey === false) {
            throw new \InvalidArgumentException('Invalid public key PEM');
        }

        $result = openssl_verify($signedInput, $derSignature, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Convert an EC P-256 JWK to PEM format.
     *
     * @param  array{kty: string, crv: string, x: string, y: string}  $jwk
     */
    public function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'EC' || ($jwk['crv'] ?? '') !== 'P-256') {
            throw new \InvalidArgumentException('Only EC P-256 keys are supported');
        }

        $x = self::base64urlDecode($jwk['x']);
        $y = self::base64urlDecode($jwk['y']);

        // Zero-pad to 32 bytes — some implementations omit leading zeros
        $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
        $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);

        if (strlen($x) !== 32 || strlen($y) !== 32) {
            throw new \InvalidArgumentException('Invalid EC P-256 key coordinates');
        }

        // ASN.1 prefix for EC P-256 public key (uncompressed point)
        // SEQUENCE { SEQUENCE { OID ecPublicKey, OID prime256v1 }, BIT STRING { 0x04 || x || y } }
        $asn1Prefix = hex2bin(
            '3059301306072a8648ce3d020106082a8648ce3d030107034200'
        );

        $uncompressedPoint = "\x04".$x.$y;
        $der = $asn1Prefix.$uncompressedPoint;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        return $pem;
    }

    public static function base64urlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }

    public static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Convert raw ECDSA R||S (64 bytes for P-256) to DER-encoded signature.
     */
    private function rawToDer(string $raw): string
    {
        if (strlen($raw) !== 64) {
            throw new \InvalidArgumentException('Invalid ES256 signature: expected 64 bytes, got '.strlen($raw));
        }

        $r = substr($raw, 0, 32);
        $s = substr($raw, 32, 32);

        $r = $this->encodeAsn1Integer($r);
        $s = $this->encodeAsn1Integer($s);

        // SEQUENCE tag (0x30) + length + r + s
        $content = $r.$s;

        return "\x30".chr(strlen($content)).$content;
    }

    /**
     * Encode a big-endian unsigned integer as ASN.1 INTEGER.
     */
    private function encodeAsn1Integer(string $bytes): string
    {
        // Remove leading zero bytes
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        // If high bit is set, prepend a zero byte (ASN.1 INTEGER is signed)
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        // INTEGER tag (0x02) + length + value
        return "\x02".chr(strlen($bytes)).$bytes;
    }
}
