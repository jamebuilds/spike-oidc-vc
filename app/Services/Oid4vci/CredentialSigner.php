<?php

namespace App\Services\Oid4vci;

use App\Services\Oid4vp\SdJwt\JwtParser;
use RuntimeException;

class CredentialSigner
{
    /**
     * Sign an SD-JWT VC (vc+sd-jwt format) using ES256.
     *
     * All subject claims are selectively disclosable.
     *
     * @param  array<string, mixed>  $subjectClaims
     * @param  array<string, mixed>|null  $holderJwk  Holder's public key JWK for cnf.jwk binding
     * @param  string|null  $holderKeyRef  Holder's DID URL for cnf.kid binding
     */
    public function sign(array $subjectClaims, string $holderDid, ?array $holderJwk = null, ?string $holderKeyRef = null): string
    {
        $keyPem = config('oid4vci.signing_key_pem');

        if (empty($keyPem)) {
            throw new RuntimeException('OID4VCI signing key not configured (OID4VCI_SIGNING_KEY_PEM)');
        }

        // Handle newline-escaped PEM from env
        $keyPem = str_replace('\\n', "\n", $keyPem);

        $privateKey = openssl_pkey_get_private($keyPem);

        if ($privateKey === false) {
            throw new RuntimeException('Invalid OID4VCI signing key: '.openssl_error_string());
        }

        // Derive issuer DID from signing key
        $issuerJwk = $this->extractPublicJwk($privateKey);
        $issuerDid = 'did:jwk:'.JwtParser::base64urlEncode(json_encode($issuerJwk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Build disclosures for each subject claim
        $disclosures = [];
        $sdHashes = [];

        foreach ($subjectClaims as $name => $value) {
            $salt = JwtParser::base64urlEncode(random_bytes(16));
            $disclosureJson = json_encode([$salt, $name, $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $disclosure = JwtParser::base64urlEncode($disclosureJson);
            $disclosures[] = $disclosure;
            $sdHashes[] = JwtParser::base64urlEncode(hash('sha256', $disclosure, true));
        }

        $header = [
            'kid' => $issuerDid.'#0',
            'typ' => 'vc+sd-jwt',
            'alg' => 'ES256',
        ];

        $now = time();
        $payload = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + (int) config('oid4vci.token_ttl_seconds', 3600),
            '_sd_alg' => 'sha-256',
            'iss' => $issuerDid,
            'vct' => config('oid4vci.issuer_url').'/'.config('oid4vci.credential_type', 'AccredifyEmployeePass'),
            '_sd' => $sdHashes,
        ];

        // Add holder key binding (cnf) — use kid for DID binding, jwk for JWK binding
        if ($holderKeyRef !== null) {
            $payload['cnf'] = ['kid' => $holderKeyRef];
        } elseif ($holderJwk !== null) {
            $payload['cnf'] = ['jwk' => $holderJwk];
        }

        $jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $headerB64 = JwtParser::base64urlEncode(json_encode($header, $jsonFlags));
        $payloadB64 = JwtParser::base64urlEncode(json_encode($payload, $jsonFlags));
        $signingInput = $headerB64.'.'.$payloadB64;

        openssl_sign($signingInput, $derSig, $privateKey, OPENSSL_ALGO_SHA256);

        // Convert DER-encoded signature to raw R||S (64 bytes for P-256)
        $rawSig = $this->derToRaw($derSig);

        $jwt = $signingInput.'.'.JwtParser::base64urlEncode($rawSig);

        // SD-JWT format: jwt~disclosure1~disclosure2~...~
        return $jwt.'~'.implode('~', $disclosures).'~';
    }

    /**
     * Extract the public key as a JWK from a private key resource.
     *
     * @return array{kty: string, crv: string, x: string, y: string}
     */
    private function extractPublicJwk(mixed $privateKey): array
    {
        $details = openssl_pkey_get_details($privateKey);

        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('Signing key must be an EC key');
        }

        return [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => JwtParser::base64urlEncode(str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)),
            'y' => JwtParser::base64urlEncode(str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT)),
        ];
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S (64 bytes for P-256).
     */
    private function derToRaw(string $derSig): string
    {
        $offset = 2;
        $offset++;
        $rLen = ord($derSig[$offset]);
        $offset++;
        $r = substr($derSig, $offset, $rLen);
        $offset += $rLen;
        $offset++;
        $sLen = ord($derSig[$offset]);
        $offset++;
        $s = substr($derSig, $offset, $sLen);

        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }
}
