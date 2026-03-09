<?php

use App\Models\Credential;
use App\Models\User;
use App\Models\WalletKey;
use App\Services\Wallet\SdJwtService;
use App\Services\Wallet\WalletKeyService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Generate a real wallet key pair
    $walletKeyService = app(WalletKeyService::class);
    $keyPair = $walletKeyService->generateKeyPair();

    $this->walletKey = WalletKey::factory()->create([
        'user_id' => $this->user->id,
        'public_jwk' => $keyPair['publicJwk'],
        'private_jwk' => json_encode($keyPair['privateJwk']),
    ]);

    // Build a real SD-JWT credential
    $sdJwtService = app(SdJwtService::class);

    $disclosure1 = $sdJwtService->base64urlEncode(json_encode(['salt1', 'given_name', 'John']));
    $disclosure2 = $sdJwtService->base64urlEncode(json_encode(['salt2', 'family_name', 'Doe']));

    $rawSdJwt = 'eyJhbGciOiJFUzI1NiJ9.eyJ2Y3QiOiJJZGVudGl0eUNyZWRlbnRpYWwifQ.stub~'.$disclosure1.'~'.$disclosure2.'~';
    $mapping = $sdJwtService->buildDisclosureMapping($rawSdJwt);

    $this->credential = Credential::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'IdentityCredential',
        'raw_sd_jwt' => $rawSdJwt,
        'disclosure_mapping' => $mapping,
        'payload_claims' => ['vct' => 'IdentityCredential'],
    ]);
});

describe('create', function () {
    it('renders the consent page with matching credentials', function () {
        $presentationDefinition = json_encode([
            'id' => 'test-definition',
            'input_descriptors' => [
                [
                    'id' => 'identity',
                    'constraints' => [
                        'fields' => [
                            ['path' => ['$.vct'], 'filter' => ['const' => 'IdentityCredential']],
                            ['path' => ['$.given_name']],
                            ['path' => ['$.family_name'], 'optional' => true],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->get('/wallet/authorizations/create?'.http_build_query([
                'client_id' => 'https://verifier.example.com',
                'nonce' => 'test-nonce-123',
                'response_uri' => 'https://verifier.example.com/callback',
                'presentation_definition' => $presentationDefinition,
            ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('wallet/authorizations/create')
            ->has('matches', 1)
            ->where('authRequest.client_id', 'https://verifier.example.com')
            ->where('authRequest.nonce', 'test-nonce-123')
            ->where('error', null)
        );
    });

    it('fetches authorization request from request_uri', function () {
        Http::fake([
            'https://verifier.example.com/request/*' => Http::response([
                'client_id' => 'https://verifier.example.com',
                'nonce' => 'fetched-nonce',
                'response_uri' => 'https://verifier.example.com/callback',
                'presentation_definition' => [
                    'id' => 'test-def',
                    'input_descriptors' => [
                        [
                            'id' => 'identity',
                            'constraints' => [
                                'fields' => [
                                    ['path' => ['$.vct'], 'filter' => ['const' => 'IdentityCredential']],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->get('/wallet/authorizations/create?'.http_build_query([
                'request_uri' => 'https://verifier.example.com/request/123',
            ]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('wallet/authorizations/create')
            ->where('authRequest.nonce', 'fetched-nonce')
            ->has('matches', 1)
        );
    });

    it('shows error for missing parameters', function () {
        $response = $this->actingAs($this->user)
            ->get('/wallet/authorizations/create');

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('wallet/authorizations/create')
            ->where('error', 'Invalid authorization request. Missing required parameters.')
        );
    });
});

describe('store', function () {
    it('submits VP token to verifier and logs the presentation', function () {
        Http::fake([
            'https://verifier.example.com/callback' => Http::response(['status' => 'ok'], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/wallet/authorizations', [
                'credential_id' => $this->credential->id,
                'selected_disclosures' => [0, 1],
                'nonce' => 'test-nonce',
                'client_id' => 'https://verifier.example.com',
                'response_uri' => 'https://verifier.example.com/callback',
                'definition_id' => 'test-def',
                'descriptor_id' => 'identity',
            ]);

        $response->assertRedirect(route('wallet.dashboard'));

        // Verify the HTTP call was made to the verifier
        Http::assertSent(function ($request) {
            return $request->url() === 'https://verifier.example.com/callback'
                && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
                && ! empty($request['vp_token'])
                && ! empty($request['presentation_submission']);
        });

        // Verify presentation log was created
        $this->assertDatabaseHas('presentation_logs', [
            'user_id' => $this->user->id,
            'credential_id' => $this->credential->id,
            'verifier_client_id' => 'https://verifier.example.com',
            'nonce' => 'test-nonce',
            'status' => 'success',
        ]);
    });

    it('logs submitted status when verifier returns non-200', function () {
        Http::fake([
            'https://verifier.example.com/callback' => Http::response(['error' => 'invalid'], 400),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/wallet/authorizations', [
                'credential_id' => $this->credential->id,
                'selected_disclosures' => [0],
                'nonce' => 'test-nonce',
                'client_id' => 'https://verifier.example.com',
                'response_uri' => 'https://verifier.example.com/callback',
            ]);

        $response->assertRedirect(route('wallet.dashboard'));

        $this->assertDatabaseHas('presentation_logs', [
            'user_id' => $this->user->id,
            'status' => 'submitted',
        ]);
    });

    it('redirects to verifier result page when error_uri is returned', function () {
        Http::fake([
            'https://verifier.example.com/callback' => Http::response([
                'error_uri' => 'https://verifier.example.com/result/abc',
            ], 400),
        ]);

        $response = $this->actingAs($this->user)
            ->post('/wallet/authorizations', [
                'credential_id' => $this->credential->id,
                'selected_disclosures' => [0],
                'nonce' => 'test-nonce',
                'client_id' => 'https://verifier.example.com',
                'response_uri' => 'https://verifier.example.com/callback',
            ]);

        $response->assertRedirect('https://verifier.example.com/result/abc');
    });

    it('validates required fields', function () {
        $response = $this->actingAs($this->user)
            ->post('/wallet/authorizations', []);

        $response->assertSessionHasErrors(['credential_id', 'selected_disclosures', 'nonce', 'client_id', 'response_uri']);
    });
});
