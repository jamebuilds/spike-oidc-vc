<?php

namespace App\Services\Oid4vci;

use App\Services\Oid4vp\SdJwt\JwtParser;
use RuntimeException;

class CredentialSigner
{
    /**
     * Sign a JWT VC (jwt_vc_json format) using ES256.
     *
     * @param  array<string, mixed>  $subjectClaims
     */
    public function sign(array $subjectClaims, string $holderDid): string
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

        $header = [
            'alg' => 'ES256',
            'typ' => 'JWT',
            'kid' => config('oid4vci.signing_key_id', 'key-1'),
        ];

        $now = time();
        $payload = [
            'iss' => config('oid4vci.issuer_url'),
            'sub' => $holderDid,
            'iat' => $now,
            'exp' => $now + config('oid4vci.token_ttl_seconds', 3600),
            'vc' => [
                '@context' => ['https://www.w3.org/2018/credentials/v1'],
                'type' => ['VerifiableCredential', config('oid4vci.credential_type', 'BankId')],
                'credentialSubject' => array_merge(
                    ['id' => $holderDid],
                    $subjectClaims,
                ),
            ],
        ];

        $headerB64 = JwtParser::base64urlEncode(json_encode($header));
        $payloadB64 = JwtParser::base64urlEncode(json_encode($payload));
        $signingInput = $headerB64.'.'.$payloadB64;

        openssl_sign($signingInput, $derSig, $privateKey, OPENSSL_ALGO_SHA256);

        // Convert DER-encoded signature to raw R||S (64 bytes for P-256)
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

        $rawSig = $r.$s;

        return $signingInput.'.'.JwtParser::base64urlEncode($rawSig);
    }
}
