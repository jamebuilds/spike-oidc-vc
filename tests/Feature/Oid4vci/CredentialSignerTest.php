<?php

use App\Services\Oid4vci\CredentialSigner;
use App\Services\Oid4vp\SdJwt\DisclosureProcessor;
use App\Services\Oid4vp\SdJwt\JwtParser;

beforeEach(function () {
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
    openssl_pkey_export($key, $pem, null, $config);

    config([
        'oid4vci.signing_key_pem' => $pem,
        'oid4vci.issuer_url' => 'http://localhost:8000',
        'oid4vci.credential_type' => 'AccredifyEmployeePass',
        'oid4vci.token_ttl_seconds' => 3600,
    ]);

    $this->signer = new CredentialSigner;
    $this->parser = new JwtParser;
    $this->processor = new DisclosureProcessor;
});

describe('sign', function () {
    it('produces a valid SD-JWT that passes signature verification', function () {
        $claims = ['given_name' => 'Alice', 'family_name' => 'Smith'];
        $sdJwt = $this->signer->sign($claims, 'did:jwk:test-holder');

        $parts = $this->processor->split($sdJwt);
        $issuerJwt = $this->parser->parse($parts['issuer_jwt']);

        // Resolve public key from did:jwk: in iss claim
        $iss = $issuerJwt['payload']['iss'];
        $jwkB64 = substr($iss, strlen('did:jwk:'));
        $jwk = json_decode(JwtParser::base64urlDecode($jwkB64), true);
        $pem = $this->parser->jwkToPem($jwk);

        expect($this->parser->verifySignature(
            $issuerJwt['signed_input'],
            $issuerJwt['signature'],
            $pem
        ))->toBeTrue();
    });

    it('survives JSON re-encoding by wallets that unescape slashes', function () {
        $claims = ['given_name' => 'Jane', 'family_name' => 'Doe'];
        $sdJwt = $this->signer->sign($claims, 'did:jwk:test-holder');

        $parts = $this->processor->split($sdJwt);

        // Simulate what the Walt ID wallet does: decode payload JSON and re-encode it.
        // Kotlin/Java JSON libraries do NOT escape forward slashes, so re-encoding
        // changes "http:\/\/" to "http://" which breaks the base64url and signature.
        $payloadJson = JwtParser::base64urlDecode(explode('.', $parts['issuer_jwt'])[1]);
        $reEncodedPayloadJson = json_encode(json_decode($payloadJson, true), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        expect($payloadJson)->toBe($reEncodedPayloadJson)
            ->and($payloadJson)->toContain('http://localhost:8000/AccredifyEmployeePass')
            ->and($payloadJson)->not->toContain('\\/');
    });

    it('produces correct SD-JWT structure with disclosures', function () {
        $claims = ['a' => '1', 'b' => '2', 'c' => '3'];
        $sdJwt = $this->signer->sign($claims, 'did:jwk:test-holder');

        $parts = $this->processor->split($sdJwt);

        expect($parts['disclosures'])->toHaveCount(3)
            ->and($parts['kb_jwt'])->toBeNull();

        $issuerJwt = $this->parser->parse($parts['issuer_jwt']);
        expect($issuerJwt['payload']['_sd'])->toHaveCount(3)
            ->and($issuerJwt['payload']['_sd_alg'])->toBe('sha-256');
    });

    it('includes cnf claim when holder JWK is provided', function () {
        $holderJwk = ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'test-x', 'y' => 'test-y'];
        $sdJwt = $this->signer->sign(['name' => 'Test'], 'did:jwk:holder', $holderJwk);

        $parts = $this->processor->split($sdJwt);
        $issuerJwt = $this->parser->parse($parts['issuer_jwt']);

        expect($issuerJwt['payload']['cnf']['jwk'])->toBe($holderJwk);
    });

    it('omits cnf claim when holder JWK is null', function () {
        $sdJwt = $this->signer->sign(['name' => 'Test'], 'did:jwk:holder');

        $parts = $this->processor->split($sdJwt);
        $issuerJwt = $this->parser->parse($parts['issuer_jwt']);

        expect($issuerJwt['payload'])->not->toHaveKey('cnf');
    });

    it('matches disclosures against _sd hashes', function () {
        $claims = ['given_name' => 'Alice', 'family_name' => 'Smith'];
        $sdJwt = $this->signer->sign($claims, 'did:jwk:test-holder');

        $parts = $this->processor->split($sdJwt);
        $issuerJwt = $this->parser->parse($parts['issuer_jwt']);

        $matched = $this->processor->matchDisclosures(
            $issuerJwt['payload']['_sd'],
            $parts['disclosures']
        );

        expect($matched)->toBe(['given_name' => 'Alice', 'family_name' => 'Smith']);
    });
});
