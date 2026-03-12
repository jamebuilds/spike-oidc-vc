<?php

namespace App\Console\Commands\Oid4vp;

use App\Services\Oid4vp\SdJwt\JwtParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GenerateTestSdJwt extends Command
{
    protected $signature = 'sdjwt:generate-test
        {--post-to= : The presentation request ID to POST the response to}
        {--nonce= : Override the nonce (defaults to nonce from cached request)}';

    protected $description = 'Generate a test SD-JWT and optionally POST it to simulate a wallet response';

    public function handle(JwtParser $jwtParser): int
    {
        // Generate EC P-256 keypair
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ];

        // Some OpenSSL installations require an explicit config path
        $opensslConf = $this->findOpensslConfig();
        if ($opensslConf !== null) {
            $config['config'] = $opensslConf;
        }

        $key = openssl_pkey_new($config);

        if ($key === false) {
            $this->error('Failed to generate EC key pair');

            return self::FAILURE;
        }

        $keyDetails = openssl_pkey_get_details($key);

        // Extract raw private key bytes
        $privateKeyD = $keyDetails['ec']['d'];
        $publicKeyX = $keyDetails['ec']['x'];
        $publicKeyY = $keyDetails['ec']['y'];

        // Determine nonce
        $requestId = $this->option('post-to');
        $nonce = $this->option('nonce');

        if ($nonce === null && $requestId !== null) {
            $requestData = Cache::get("oid4vp:request:{$requestId}");
            if ($requestData) {
                $nonce = $requestData['nonce'] ?? 'test-nonce';
            } else {
                $this->warn('Request not found in cache, using default nonce');
                $nonce = 'test-nonce';
            }
        } elseif ($nonce === null) {
            $nonce = 'test-nonce';
        }

        // Build issuer JWT
        $issuerHeader = ['alg' => 'ES256', 'typ' => 'vc+sd-jwt'];
        $disclosures = $this->buildDisclosures();

        $sdHashes = array_map(
            fn (string $d): string => JwtParser::base64urlEncode(hash('sha256', $d, true)),
            $disclosures
        );

        $issuerPayload = [
            'iss' => 'https://issuer.example.com',
            'iat' => time(),
            'exp' => time() + 3600,
            'vct' => 'urn:eudi:pid:1',
            'cnf' => [
                'jwk' => [
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => JwtParser::base64urlEncode($publicKeyX),
                    'y' => JwtParser::base64urlEncode($publicKeyY),
                ],
            ],
            '_sd_alg' => 'sha-256',
            '_sd' => $sdHashes,
        ];

        $issuerJwt = $this->signJwt($issuerHeader, $issuerPayload, $key);

        // Build Key Binding JWT
        $kbHeader = ['alg' => 'ES256', 'typ' => 'kb+jwt'];
        $kbPayload = [
            'nonce' => $nonce,
            'aud' => $requestId ? url("/oid4vp/{$requestId}") : 'https://verifier.example.com',
            'iat' => time(),
            'sd_hash' => JwtParser::base64urlEncode(
                hash('sha256', $issuerJwt.'~'.implode('~', $disclosures).'~', true)
            ),
        ];

        $kbJwt = $this->signJwt($kbHeader, $kbPayload, $key);

        // Assemble SD-JWT: issuer_jwt~disclosure1~disclosure2~...~kb_jwt
        $sdJwt = $issuerJwt.'~'.implode('~', $disclosures).'~'.$kbJwt;

        $this->info('Generated SD-JWT:');
        $this->line($sdJwt);
        $this->newLine();

        // Output public key PEM for config
        $exportConfig = $opensslConf !== null ? ['config' => $opensslConf] : [];
        openssl_pkey_export($key, $privateKeyPem, null, $exportConfig);
        $publicKeyPem = $keyDetails['key'];
        $this->info('Public Key PEM (set as SDJWT_ISSUER_PUBLIC_KEY):');
        $this->line($publicKeyPem);

        // Post to endpoint if requested
        if ($requestId !== null) {
            $responseUri = url("/oid4vp/{$requestId}/response");
            $this->info("POSTing to: {$responseUri}");

            $response = Http::asForm()->post($responseUri, [
                'vp_token' => $sdJwt,
            ]);

            if ($response->successful()) {
                $this->info('Response posted successfully: '.$response->body());
            } else {
                $this->error('Failed to post response: '.$response->status().' '.$response->body());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Build test disclosures for sample claims.
     *
     * @return array<int, string>
     */
    private function buildDisclosures(): array
    {
        $claims = [
            ['employeeId', 'EMP-TEST1234'],
            ['firstName', 'Jane'],
            ['lastName', 'Doe'],
            ['dateOfBirth', '1992-07-20'],
            ['nric', 'S9012345A'],
        ];

        return array_map(function (array $claim): string {
            $salt = bin2hex(random_bytes(16));

            return JwtParser::base64urlEncode(json_encode([$salt, $claim[0], $claim[1]]));
        }, $claims);
    }

    /**
     * Sign a JWT with ES256 using the given EC private key.
     *
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $payload
     */
    private function signJwt(array $header, array $payload, \OpenSSLAsymmetricKey $key): string
    {
        $headerB64 = JwtParser::base64urlEncode(json_encode($header));
        $payloadB64 = JwtParser::base64urlEncode(json_encode($payload));
        $signingInput = $headerB64.'.'.$payloadB64;

        openssl_sign($signingInput, $derSignature, $key, OPENSSL_ALGO_SHA256);

        // Convert DER signature to raw R||S format
        $rawSignature = $this->derToRaw($derSignature);

        return $signingInput.'.'.JwtParser::base64urlEncode($rawSignature);
    }

    /**
     * Find a working OpenSSL config file path.
     */
    private function findOpensslConfig(): ?string
    {
        $paths = [
            '/opt/homebrew/etc/openssl@3/openssl.cnf',
            '/opt/homebrew/etc/openssl/openssl.cnf',
            '/usr/local/etc/openssl@3/openssl.cnf',
            '/usr/local/etc/openssl/openssl.cnf',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Convert DER-encoded ECDSA signature to raw R||S (64 bytes for P-256).
     */
    private function derToRaw(string $der): string
    {
        $offset = 2; // Skip SEQUENCE tag and length

        // Read R
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Expected INTEGER tag for R');
        }
        $offset++;
        $rLen = ord($der[$offset]);
        $offset++;
        $r = substr($der, $offset, $rLen);
        $offset += $rLen;

        // Read S
        if (ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Expected INTEGER tag for S');
        }
        $offset++;
        $sLen = ord($der[$offset]);
        $offset++;
        $s = substr($der, $offset, $sLen);

        // Pad or trim to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r.$s;
    }
}
