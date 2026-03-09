<?php

use App\Services\Oid4vp\SdJwt\JwtParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('verifies a valid SD-JWT response end-to-end', function () {
    // Set up: create a presentation request
    $response = $this->postJson(route('oid4vp.store'));
    $response->assertOk();

    $requestId = $response->json('id');
    $requestData = Cache::get("oid4vp:request:{$requestId}");
    $nonce = $requestData['nonce'];

    // Generate a test SD-JWT
    $sdJwt = buildTestSdJwt($nonce, $requestId);

    // Skip signature verification for this test
    config(['oid4vp.skip_signature_verification' => true]);

    // Post the response
    $postResponse = $this->post(route('oid4vp.response', $requestId), [
        'vp_token' => $sdJwt['compact'],
        'presentation_submission' => json_encode([
            'id' => Str::uuid()->toString(),
            'definition_id' => $requestId,
            'descriptor_map' => [[
                'id' => 'bankid_credential',
                'format' => 'jwt_vc_json',
                'path' => '$',
            ]],
        ]),
    ]);

    $postResponse->assertOk();

    // Check the status
    $statusResponse = $this->getJson(route('oid4vp.status', $requestId));
    $statusResponse->assertOk();

    $status = $statusResponse->json();
    expect($status['status'])->toBe('complete');
    expect($status['verification'])->not->toBeNull();
    expect($status['verification']['is_valid'])->toBeTrue();
    expect($status['verification']['vct'])->toBe('urn:eudi:pid:1');
    expect($status['verification']['disclosed_claims'])->toHaveKey('given_name', 'John');
    expect($status['verification']['nonce'])->toBe($nonce);
});

it('rejects an SD-JWT with wrong nonce', function () {
    $response = $this->postJson(route('oid4vp.store'));
    $requestId = $response->json('id');

    $sdJwt = buildTestSdJwt('wrong-nonce', $requestId);

    config(['oid4vp.skip_signature_verification' => true]);

    $this->post(route('oid4vp.response', $requestId), [
        'vp_token' => $sdJwt['compact'],
        'presentation_submission' => json_encode([
            'id' => Str::uuid()->toString(),
            'definition_id' => $requestId,
            'descriptor_map' => [[
                'id' => 'bankid_credential',
                'format' => 'jwt_vc_json',
                'path' => '$',
            ]],
        ]),
    ]);

    $statusResponse = $this->getJson(route('oid4vp.status', $requestId));
    $status = $statusResponse->json();

    expect($status['verification']['is_valid'])->toBeFalse();
    expect($status['verification']['errors'])->not->toBeEmpty();
});

it('handles a response without vp_token gracefully', function () {
    $response = $this->postJson(route('oid4vp.store'));
    $requestId = $response->json('id');

    $this->post(route('oid4vp.response', $requestId), [
        'state' => 'some-state',
    ]);

    $statusResponse = $this->getJson(route('oid4vp.status', $requestId));
    $status = $statusResponse->json();

    expect($status['status'])->toBe('complete');
    expect($status['verification'])->toBeNull();
    expect($status['data'])->toHaveKey('state', 'some-state');
});

it('returns 404 for expired request', function () {
    $response = $this->post('/oid4vp/nonexistent-id/response', [
        'vp_token' => 'anything',
    ]);

    $response->assertNotFound();
});

function generateTestEcKey(): OpenSSLAsymmetricKey
{
    $config = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ];

    foreach ([
        '/opt/homebrew/etc/openssl@3/openssl.cnf',
        '/opt/homebrew/etc/openssl/openssl.cnf',
        '/usr/local/etc/openssl@3/openssl.cnf',
        '/usr/local/etc/openssl/openssl.cnf',
    ] as $path) {
        if (file_exists($path)) {
            $config['config'] = $path;
            break;
        }
    }

    $key = openssl_pkey_new($config);

    if ($key === false) {
        throw new RuntimeException('Failed to generate EC key: '.openssl_error_string());
    }

    return $key;
}

/**
 * Build a test SD-JWT for integration testing.
 *
 * @return array{compact: string, public_key_pem: string}
 */
function buildTestSdJwt(string $nonce, string $requestId): array
{
    $key = generateTestEcKey();
    $details = openssl_pkey_get_details($key);

    // Build disclosures
    $disclosureClaims = [
        ['given_name', 'John'],
        ['family_name', 'Doe'],
    ];

    $disclosures = [];
    foreach ($disclosureClaims as $claim) {
        $salt = bin2hex(random_bytes(16));
        $disclosures[] = JwtParser::base64urlEncode(json_encode([$salt, $claim[0], $claim[1]]));
    }

    $sdHashes = array_map(
        fn (string $d): string => JwtParser::base64urlEncode(hash('sha256', $d, true)),
        $disclosures
    );

    // Build issuer JWT
    $issuerHeader = ['alg' => 'ES256', 'typ' => 'vc+sd-jwt'];
    $issuerPayload = [
        'iss' => 'https://issuer.example.com',
        'iat' => time(),
        'exp' => time() + 3600,
        'vct' => 'urn:eudi:pid:1',
        '_sd_alg' => 'sha-256',
        '_sd' => $sdHashes,
    ];

    $issuerJwt = testSignJwt($issuerHeader, $issuerPayload, $key);

    // Build KB-JWT
    $kbHeader = ['alg' => 'ES256', 'typ' => 'kb+jwt'];
    $kbPayload = [
        'nonce' => $nonce,
        'aud' => url("/oid4vp/{$requestId}/response"),
        'iat' => time(),
    ];

    $kbJwt = testSignJwt($kbHeader, $kbPayload, $key);

    $compact = $issuerJwt.'~'.implode('~', $disclosures).'~'.$kbJwt;

    return [
        'compact' => $compact,
        'public_key_pem' => $details['key'],
    ];
}

function testSignJwt(array $header, array $payload, OpenSSLAsymmetricKey $key): string
{
    $headerB64 = JwtParser::base64urlEncode(json_encode($header));
    $payloadB64 = JwtParser::base64urlEncode(json_encode($payload));
    $signingInput = $headerB64.'.'.$payloadB64;

    openssl_sign($signingInput, $derSig, $key, OPENSSL_ALGO_SHA256);

    // Convert DER to raw R||S
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
