<?php

use App\Services\Oid4vp\SdJwt\DisclosureProcessor;
use App\Services\Oid4vp\SdJwt\JwtParser;

beforeEach(function () {
    $this->processor = new DisclosureProcessor;
});

describe('split', function () {
    it('splits an SD-JWT with disclosures and KB-JWT', function () {
        $issuerJwt = 'header.payload.signature';
        $disclosure1 = JwtParser::base64urlEncode(json_encode(['salt1', 'name', 'John']));
        $disclosure2 = JwtParser::base64urlEncode(json_encode(['salt2', 'age', 30]));
        $kbJwt = 'kb-header.kb-payload.kb-signature';

        $sdJwt = "{$issuerJwt}~{$disclosure1}~{$disclosure2}~{$kbJwt}";

        $result = $this->processor->split($sdJwt);

        expect($result['issuer_jwt'])->toBe($issuerJwt);
        expect($result['disclosures'])->toHaveCount(2);
        expect($result['disclosures'][0])->toBe($disclosure1);
        expect($result['disclosures'][1])->toBe($disclosure2);
        expect($result['kb_jwt'])->toBe($kbJwt);
    });

    it('handles trailing tilde (no KB-JWT)', function () {
        $sdJwt = 'header.payload.signature~disclosure1~';

        $result = $this->processor->split($sdJwt);

        expect($result['issuer_jwt'])->toBe('header.payload.signature');
        expect($result['disclosures'])->toHaveCount(1);
        expect($result['kb_jwt'])->toBeNull();
    });

    it('handles JWT only (no disclosures or KB-JWT)', function () {
        $sdJwt = 'header.payload.signature';

        $result = $this->processor->split($sdJwt);

        expect($result['issuer_jwt'])->toBe('header.payload.signature');
        expect($result['disclosures'])->toBeEmpty();
        expect($result['kb_jwt'])->toBeNull();
    });
});

describe('decodeDisclosure', function () {
    it('decodes a valid disclosure', function () {
        $disclosure = JwtParser::base64urlEncode(json_encode(['salt123', 'given_name', 'John']));

        [$salt, $name, $value] = $this->processor->decodeDisclosure($disclosure);

        expect($salt)->toBe('salt123');
        expect($name)->toBe('given_name');
        expect($value)->toBe('John');
    });

    it('handles object values in disclosures', function () {
        $disclosure = JwtParser::base64urlEncode(json_encode(['salt', 'address', ['street' => '123 Main St']]));

        [$salt, $name, $value] = $this->processor->decodeDisclosure($disclosure);

        expect($name)->toBe('address');
        expect($value)->toBe(['street' => '123 Main St']);
    });

    it('throws on invalid disclosure', function () {
        $disclosure = JwtParser::base64urlEncode(json_encode(['only_two']));
        $this->processor->decodeDisclosure($disclosure);
    })->throws(InvalidArgumentException::class, 'at least 3 elements');
});

describe('hashDisclosure', function () {
    it('produces a base64url-encoded SHA-256 hash', function () {
        $disclosure = 'test-disclosure-string';
        $hash = $this->processor->hashDisclosure($disclosure);

        $expectedHash = JwtParser::base64urlEncode(hash('sha256', $disclosure, true));
        expect($hash)->toBe($expectedHash);
    });

    it('produces different hashes for different inputs', function () {
        $hash1 = $this->processor->hashDisclosure('disclosure-a');
        $hash2 = $this->processor->hashDisclosure('disclosure-b');

        expect($hash1)->not->toBe($hash2);
    });
});

describe('matchDisclosures', function () {
    it('matches disclosures against _sd hashes', function () {
        $d1 = JwtParser::base64urlEncode(json_encode(['salt1', 'given_name', 'John']));
        $d2 = JwtParser::base64urlEncode(json_encode(['salt2', 'family_name', 'Doe']));

        $hash1 = $this->processor->hashDisclosure($d1);
        $hash2 = $this->processor->hashDisclosure($d2);

        $sdArray = [$hash1, $hash2, 'unmatched-hash'];
        $disclosures = [$d1, $d2];

        $matched = $this->processor->matchDisclosures($sdArray, $disclosures);

        expect($matched)->toBe(['given_name' => 'John', 'family_name' => 'Doe']);
    });

    it('ignores disclosures not in _sd array', function () {
        $d1 = JwtParser::base64urlEncode(json_encode(['salt1', 'name', 'John']));
        $d2 = JwtParser::base64urlEncode(json_encode(['salt2', 'secret', 'hidden']));

        $hash1 = $this->processor->hashDisclosure($d1);

        $sdArray = [$hash1]; // Only d1 hash is in _sd
        $matched = $this->processor->matchDisclosures($sdArray, [$d1, $d2]);

        expect($matched)->toBe(['name' => 'John']);
    });
});

describe('reconstructClaims', function () {
    it('merges disclosed claims and removes _sd fields', function () {
        $payload = [
            'iss' => 'https://issuer.example.com',
            'vct' => 'urn:eudi:pid:1',
            '_sd' => ['hash1', 'hash2'],
            '_sd_alg' => 'sha-256',
        ];

        $disclosed = ['given_name' => 'John', 'family_name' => 'Doe'];

        $claims = $this->processor->reconstructClaims($payload, $disclosed);

        expect($claims)->toHaveKey('iss', 'https://issuer.example.com');
        expect($claims)->toHaveKey('vct', 'urn:eudi:pid:1');
        expect($claims)->toHaveKey('given_name', 'John');
        expect($claims)->toHaveKey('family_name', 'Doe');
        expect($claims)->not->toHaveKey('_sd');
        expect($claims)->not->toHaveKey('_sd_alg');
    });
});
