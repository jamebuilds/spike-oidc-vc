<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\SubmitAuthorizationRequest;
use App\Models\Credential;
use App\Models\PresentationLog;
use App\Services\Wallet\PresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizationController extends Controller
{
    public function __construct(private PresentationService $presentationService) {}

    public function create(Request $request): Response
    {
        $requestUri = $request->query('request_uri');
        $clientId = $request->query('client_id');
        $nonce = $request->query('nonce');
        $responseUri = $request->query('response_uri');
        $presentationDefinition = $request->query('presentation_definition');
        $state = $request->query('state');

        // If request_uri is provided, fetch the authorization request
        if ($requestUri) {
            $response = Http::get($requestUri);
            $authRequest = $response->json();

            $clientId = $authRequest['client_id'] ?? $clientId;
            $nonce = $authRequest['nonce'] ?? $nonce;
            $responseUri = $authRequest['response_uri'] ?? $responseUri;
            $state = $authRequest['state'] ?? $state;

            $presentationDefinition = $authRequest['presentation_definition']
                ?? (isset($authRequest['presentation_definition_uri'])
                    ? Http::get($authRequest['presentation_definition_uri'])->json()
                    : null);
        } else {
            // Handle inline presentation_definition or presentation_definition_uri
            if (is_string($presentationDefinition)) {
                $presentationDefinition = json_decode($presentationDefinition, true);
            }

            if (! $presentationDefinition) {
                $presentationDefinitionUri = $request->query('presentation_definition_uri');

                if ($presentationDefinitionUri) {
                    $presentationDefinition = Http::get($presentationDefinitionUri)->json();
                }
            }
        }

        if (! $presentationDefinition || ! $clientId || ! $nonce || ! $responseUri) {
            return Inertia::render('wallet/authorizations/create', [
                'error' => 'Invalid authorization request. Missing required parameters.',
                'matches' => [],
                'authRequest' => [],
            ]);
        }

        $credentials = $request->user()->credentials()->get();
        $matches = $this->presentationService->matchCredentials($credentials, $presentationDefinition);

        // Categorize disclosures for each match
        $matchesWithDisclosures = $matches->map(function ($match) {
            $categorized = $this->presentationService->categorizeDisclosures(
                $match['credential'],
                $match['inputDescriptor'],
            );

            return [
                'credential' => [
                    'id' => $match['credential']->id,
                    'issuer' => $match['credential']->issuer,
                    'type' => $match['credential']->type,
                    'payload_claims' => $match['credential']->payload_claims,
                    'disclosure_mapping' => $match['credential']->disclosure_mapping,
                ],
                'inputDescriptor' => $match['inputDescriptor'],
                'requiredDisclosures' => $categorized['required'],
                'selectableDisclosures' => $categorized['selectable'],
            ];
        });

        return Inertia::render('wallet/authorizations/create', [
            'matches' => $matchesWithDisclosures->values(),
            'authRequest' => [
                'client_id' => $clientId,
                'nonce' => $nonce,
                'response_uri' => $responseUri,
                'state' => $state,
                'presentation_definition' => $presentationDefinition,
            ],
            'error' => null,
        ]);
    }

    public function store(SubmitAuthorizationRequest $request): \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $validated = $request->validated();
        $user = $request->user();

        $credential = Credential::findOrFail($validated['credential_id']);
        abort_unless($credential->user_id === $user->id, 403);

        $walletKey = $user->walletKey;
        abort_unless($walletKey !== null, 400, 'No wallet key found.');

        $selectedIndices = $validated['selected_disclosures'];
        $nonce = $validated['nonce'];
        $clientId = $validated['client_id'];
        $responseUri = $validated['response_uri'];
        $state = $validated['state'] ?? null;

        // Build the VP token
        $vpToken = $this->presentationService->buildVpToken(
            $credential,
            $selectedIndices,
            $walletKey,
            $clientId,
            $nonce,
        );

        // Build presentation submission
        $definitionId = $validated['definition_id'] ?? 'default';
        $descriptorId = $validated['descriptor_id'] ?? 'default';
        $presentationSubmission = $this->presentationService->buildPresentationSubmission($definitionId, $descriptorId);

        // POST to verifier's response_uri
        $formData = [
            'vp_token' => $vpToken,
            'presentation_submission' => json_encode($presentationSubmission),
        ];

        if ($state) {
            $formData['state'] = $state;
        }

        $verifierResponse = Http::asForm()->post($responseUri, $formData);

        \Illuminate\Support\Facades\Log::debug('OID4VP: verifier response', [
            'status' => $verifierResponse->status(),
            'body' => $verifierResponse->body(),
            'vp_token_preview' => substr($vpToken, 0, 100).'...',
        ]);

        // Determine disclosed claim names
        $disclosureMapping = $credential->disclosure_mapping ?? [];
        $disclosedClaims = collect($selectedIndices)
            ->filter(fn ($i) => isset($disclosureMapping[$i]))
            ->map(fn ($i) => $disclosureMapping[$i]['claimName'])
            ->values()
            ->toArray();

        // Check for redirect URI in the response (verifiers may use redirect_uri or error_uri)
        $responseData = $verifierResponse->json() ?? [];
        $redirectUri = $responseData['redirect_uri'] ?? $responseData['error_uri'] ?? null;

        // Log the presentation
        $status = $verifierResponse->successful() ? 'success' : 'submitted';

        PresentationLog::create([
            'user_id' => $user->id,
            'credential_id' => $credential->id,
            'verifier_client_id' => $clientId,
            'nonce' => $nonce,
            'disclosed_claims' => $disclosedClaims,
            'response_uri' => $responseUri,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        // Redirect to verifier's result page if provided
        // Use Inertia::location() for external URLs to force full page navigation
        if ($redirectUri) {
            return Inertia::location($redirectUri);
        }

        if ($verifierResponse->successful()) {
            return redirect()->route('wallet.dashboard')
                ->with('success', 'Presentation submitted successfully.');
        }

        return redirect()->route('wallet.dashboard')
            ->with('error', 'Verifier rejected the presentation: '.$verifierResponse->body());
    }
}
