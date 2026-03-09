<?php

use App\Services\Oid4vp\SdJwt\JwtParser;

beforeEach(function () {
    $this->parser = new JwtParser;
});

describe('base64url encoding/decoding', function () {
    it('round-trips binary data', function () {
        $data = random_bytes(32);
        $encoded = JwtParser::base64urlEncode($data);
        $decoded = JwtParser::base64urlDecode($encoded);

        expect($decoded)->toBe($data);
    });

    it('does not use + / or = characters', function () {
        // Data that would produce +, /, = in standard base64
        $data = hex2bin('fbff3efc');
        $encoded = JwtParser::base64urlEncode($data);

        expect($encoded)->not->toContain('+');
        expect($encoded)->not->toContain('/');
        expect($encoded)->not->toContain('=');
    });

    it('decodes standard base64url without padding', function () {
        $encoded = JwtParser::base64urlEncode('hello world');
        expect(JwtParser::base64urlDecode($encoded))->toBe('hello world');
    });
});

describe('parse', function () {
    it('parses a valid compact JWS', function () {
        $header = JwtParser::base64urlEncode(json_encode(['alg' => 'ES256', 'typ' => 'JWT']));
        $payload = JwtParser::base64urlEncode(json_encode(['sub' => '1234', 'name' => 'Test']));
        $signature = JwtParser::base64urlEncode('fake-signature');

        $jws = "{$header}.{$payload}.{$signature}";
        $result = $this->parser->parse($jws);

        expect($result['header'])->toBe(['alg' => 'ES256', 'typ' => 'JWT']);
        expect($result['payload'])->toBe(['sub' => '1234', 'name' => 'Test']);
        expect($result['signed_input'])->toBe("{$header}.{$payload}");
    });

    it('throws on invalid JWS with wrong part count', function () {
        $this->parser->parse('only.two');
    })->throws(InvalidArgumentException::class, 'expected 3 dot-separated parts');

    it('throws on non-JSON header', function () {
        $header = JwtParser::base64urlEncode('not-json');
        $payload = JwtParser::base64urlEncode(json_encode(['sub' => '1']));
        $sig = JwtParser::base64urlEncode('sig');

        $this->parser->parse("{$header}.{$payload}.{$sig}");
    })->throws(InvalidArgumentException::class, 'failed to decode header or payload');
});

describe('ES256 signature verification', function () {
    it('verifies a valid ES256 signature', function () {
        $key = generateEcKey();

        $details = openssl_pkey_get_details($key);
        $publicKeyPem = $details['key'];

        $data = 'test-signing-input';
        openssl_sign($data, $derSig, $key, OPENSSL_ALGO_SHA256);

        // Convert DER to raw R||S
        $rawSig = derToRaw($derSig);

        expect($this->parser->verifySignature($data, $rawSig, $publicKeyPem))->toBeTrue();
    });

    it('rejects an invalid signature', function () {
        $key = generateEcKey();

        $details = openssl_pkey_get_details($key);
        $publicKeyPem = $details['key'];

        $invalidSig = str_repeat("\x00", 64);

        expect($this->parser->verifySignature('test', $invalidSig, $publicKeyPem))->toBeFalse();
    });

    it('throws on invalid signature length', function () {
        $key = generateEcKey();
        $details = openssl_pkey_get_details($key);

        $this->parser->verifySignature('test', 'short', $details['key']);
    })->throws(InvalidArgumentException::class, 'expected 64 bytes');
});

describe('jwkToPem', function () {
    it('converts a valid EC P-256 JWK to PEM', function () {
        $key = generateEcKey();
        $details = openssl_pkey_get_details($key);

        $jwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => JwtParser::base64urlEncode($details['ec']['x']),
            'y' => JwtParser::base64urlEncode($details['ec']['y']),
        ];

        $pem = $this->parser->jwkToPem($jwk);

        expect($pem)->toContain('-----BEGIN PUBLIC KEY-----');
        expect($pem)->toContain('-----END PUBLIC KEY-----');

        // Verify it's a valid PEM by loading it
        $loadedKey = openssl_pkey_get_public($pem);
        expect($loadedKey)->not->toBeFalse();
    });

    it('rejects non-EC keys', function () {
        $this->parser->jwkToPem(['kty' => 'RSA', 'crv' => 'P-256', 'x' => 'a', 'y' => 'b']);
    })->throws(InvalidArgumentException::class, 'Only EC P-256');

    it('rejects non-P-256 curves', function () {
        $this->parser->jwkToPem(['kty' => 'EC', 'crv' => 'P-384', 'x' => 'a', 'y' => 'b']);
    })->throws(InvalidArgumentException::class, 'Only EC P-256');
});

function generateEcKey(): OpenSSLAsymmetricKey
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
 * Helper: Convert DER ECDSA signature to raw R||S.
 */
function derToRaw(string $der): string
{
    $offset = 2;

    $offset++; // skip R INTEGER tag
    $rLen = ord($der[$offset]);
    $offset++;
    $r = substr($der, $offset, $rLen);
    $offset += $rLen;

    $offset++; // skip S INTEGER tag
    $sLen = ord($der[$offset]);
    $offset++;
    $s = substr($der, $offset, $sLen);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r.$s;
}
