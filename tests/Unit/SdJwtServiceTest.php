<?php

use App\Services\Wallet\SdJwtService;

beforeEach(function () {
    $this->service = new SdJwtService;
});

describe('parse', function () {
    it('splits a simple SD-JWT into issuer JWT and disclosures', function () {
        $rawSdJwt = 'eyJhbGciOiJFUzI1NiJ9.eyJ0ZXN0IjoidmFsdWUifQ.signature~disc1~disc2~';

        $result = $this->service->parse($rawSdJwt);

        expect($result['issuerJwt'])->toBe('eyJhbGciOiJFUzI1NiJ9.eyJ0ZXN0IjoidmFsdWUifQ.signature');
        expect($result['disclosures'])->toBe(['disc1', 'disc2']);
        expect($result['keyBindingJwt'])->toBeNull();
    });

    it('detects a key binding JWT', function () {
        $rawSdJwt = 'header.payload.sig~disc1~kb.header.sig';

        $result = $this->service->parse($rawSdJwt);

        expect($result['issuerJwt'])->toBe('header.payload.sig');
        expect($result['disclosures'])->toBe(['disc1']);
        expect($result['keyBindingJwt'])->toBe('kb.header.sig');
    });

    it('handles SD-JWT with no disclosures', function () {
        $rawSdJwt = 'header.payload.sig~';

        $result = $this->service->parse($rawSdJwt);

        expect($result['issuerJwt'])->toBe('header.payload.sig');
        expect($result['disclosures'])->toBe([]);
        expect($result['keyBindingJwt'])->toBeNull();
    });
});

describe('decodeJwtPayload', function () {
    it('decodes a JWT payload', function () {
        $payload = ['sub' => '1234', 'name' => 'Test'];
        $encodedPayload = $this->service->base64urlEncode(json_encode($payload));
        $jwt = 'eyJhbGciOiJFUzI1NiJ9.'.$encodedPayload.'.signature';

        $result = $this->service->decodeJwtPayload($jwt);

        expect($result)->toBe($payload);
    });

    it('returns empty array for invalid JWT', function () {
        $result = $this->service->decodeJwtPayload('invalid');

        expect($result)->toBe([]);
    });
});

describe('decodeDisclosure', function () {
    it('decodes a disclosure from base64url', function () {
        $disclosure = ['salt123', 'given_name', 'John'];
        $encoded = $this->service->base64urlEncode(json_encode($disclosure));

        $result = $this->service->decodeDisclosure($encoded);

        expect($result)->toBe($disclosure);
    });
});

describe('computeDisclosureDigest', function () {
    it('computes a SHA-256 digest of a disclosure', function () {
        $encoded = $this->service->base64urlEncode(json_encode(['salt', 'name', 'value']));

        $digest = $this->service->computeDisclosureDigest($encoded);

        expect($digest)->toBeString();
        expect(strlen($digest))->toBeGreaterThan(0);
    });

    it('produces consistent digests for the same input', function () {
        $encoded = $this->service->base64urlEncode(json_encode(['salt', 'name', 'value']));

        $digest1 = $this->service->computeDisclosureDigest($encoded);
        $digest2 = $this->service->computeDisclosureDigest($encoded);

        expect($digest1)->toBe($digest2);
    });
});

describe('buildDisclosureMapping', function () {
    it('builds a mapping from a raw SD-JWT', function () {
        $disclosure1 = $this->service->base64urlEncode(json_encode(['salt1', 'given_name', 'John']));
        $disclosure2 = $this->service->base64urlEncode(json_encode(['salt2', 'family_name', 'Doe']));

        $rawSdJwt = 'header.payload.sig~'.$disclosure1.'~'.$disclosure2.'~';

        $mapping = $this->service->buildDisclosureMapping($rawSdJwt);

        expect($mapping)->toHaveCount(2);
        expect($mapping[0]['claimName'])->toBe('given_name');
        expect($mapping[0]['claimValue'])->toBe('John');
        expect($mapping[0]['encoded'])->toBe($disclosure1);
        expect($mapping[0]['digest'])->toBeString();
        expect($mapping[1]['claimName'])->toBe('family_name');
        expect($mapping[1]['claimValue'])->toBe('Doe');
    });
});

describe('buildPresentationSdJwt', function () {
    it('reassembles a presentation SD-JWT with selected disclosures and KB-JWT', function () {
        $result = $this->service->buildPresentationSdJwt(
            'issuer.jwt.sig',
            ['disc1', 'disc2'],
            'kb.jwt.sig',
        );

        expect($result)->toBe('issuer.jwt.sig~disc1~disc2~kb.jwt.sig');
    });

    it('handles empty disclosures', function () {
        $result = $this->service->buildPresentationSdJwt(
            'issuer.jwt.sig',
            [],
            'kb.jwt.sig',
        );

        expect($result)->toBe('issuer.jwt.sig~kb.jwt.sig');
    });
});

describe('computeSdHash', function () {
    it('computes a hash of the presentation without KB-JWT', function () {
        $presentation = 'issuer.jwt.sig~disc1~disc2~';

        $hash = $this->service->computeSdHash($presentation);

        expect($hash)->toBeString();
        expect(strlen($hash))->toBeGreaterThan(0);
    });
});

describe('base64url encoding', function () {
    it('roundtrips correctly', function () {
        $data = 'Hello, World!';

        $encoded = $this->service->base64urlEncode($data);
        $decoded = $this->service->base64urlDecode($encoded);

        expect($decoded)->toBe($data);
        expect($encoded)->not->toContain('+');
        expect($encoded)->not->toContain('/');
        expect($encoded)->not->toContain('=');
    });
});
