<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\CredentialSigner;
use App\Services\Oid4vci\IssuanceSession;
use App\Services\Oid4vp\SdJwt\JwtParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CredentialController extends Controller
{
    public function __construct(
        private IssuanceSession $session,
        private CredentialSigner $signer,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        Log::info('OID4VCI Credential request received', [
            'has_bearer' => ! empty($request->bearerToken()),
            'has_proof' => ! empty($request->input('proof')),
            'has_proofs' => ! empty($request->input('proofs')),
            'has_credential_configuration_id' => ! empty($request->input('credential_configuration_id')),
            'has_format' => ! empty($request->input('format')),
            'credential_configuration_id' => $request->input('credential_configuration_id'),
            'format' => $request->input('format'),
            'content_type' => $request->header('Content-Type'),
            'request_keys' => array_keys($request->all()),
        ]);

        try {
            return $this->handleCredentialRequest($request);
        } catch (\Throwable $e) {
            Log::error('OID4VCI Credential error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'server_error',
                'error_description' => $e->getMessage(),
            ], 500);
        }
    }

    private function handleCredentialRequest(Request $request): JsonResponse
    {
        // Extract Bearer token
        $accessToken = $request->bearerToken();

        if (empty($accessToken)) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Missing access token',
            ], 401);
        }

        $tokenData = $this->session->findByAccessToken($accessToken);

        if ($tokenData === null) {
            return response()->json([
                'error' => 'invalid_token',
                'error_description' => 'Invalid or expired access token',
            ], 401);
        }

        $offer = $this->session->findOrFail($tokenData['offer_id']);

        // Always use Draft 14 response format — our metadata advertises Draft 14,
        // so all connecting wallets expect the Draft 14 credentials array response.
        $usedDraft14 = true;
        $proofs = $request->input('proofs');
        $proof = $request->input('proof');

        if (! empty($proofs) && ! empty($proofs['jwt'][0])) {
            // Draft 14: "proofs": { "jwt": ["eyJ..."] }
            $proofJwt = $proofs['jwt'][0];
        } elseif (! empty($proof) && ($proof['proof_type'] ?? null) === 'jwt' && ! empty($proof['jwt'])) {
            // Draft 13: "proof": { "proof_type": "jwt", "jwt": "eyJ..." }
            $proofJwt = $proof['jwt'];
        } else {
            return response()->json([
                'error' => 'invalid_proof',
                'error_description' => 'Missing or invalid proof. Expected proof_type=jwt.',
            ], 400);
        }

        // Parse the proof JWT to extract holder DID (we don't verify its signature in this spike)
        $parser = new JwtParser;
        $parsed = $parser->parse($proofJwt);
        $proofHeader = $parsed['header'];
        $proofPayload = $parsed['payload'];

        // Validate nonce
        $expectedNonce = $tokenData['c_nonce'];

        if (($proofPayload['nonce'] ?? null) !== $expectedNonce) {
            return response()->json([
                'error' => 'invalid_proof',
                'error_description' => 'Invalid c_nonce in proof JWT',
            ], 400);
        }

        // Extract holder DID from proof header (kid or iss)
        $holderDid = $proofHeader['kid'] ?? $proofPayload['iss'] ?? 'did:key:unknown';

        // Strip key fragment from kid (e.g., "did:key:z123#z123" -> "did:key:z123")
        if (str_contains($holderDid, '#')) {
            $holderDid = substr($holderDid, 0, strpos($holderDid, '#'));
        }

        // Extract holder JWK for key binding (cnf claim)
        $holderJwk = $this->extractHolderJwk($proofHeader, $holderDid);

        // Sign and issue the SD-JWT credential
        $credential = $this->signer->sign($offer['subject_claims'], $holderDid, $holderJwk);

        // Mark issuance as complete
        $this->session->complete($tokenData['offer_id'], [
            'holder_did' => $holderDid,
            'credential_type' => config('oid4vci.credential_type', 'AccredifyEmployeePass'),
        ]);

        // Generate a fresh c_nonce for potential follow-up requests
        $newNonce = bin2hex(random_bytes(16));

        Log::info('OID4VCI Credential issued', [
            'holder_did' => $holderDid,
            'has_cnf' => $holderJwk !== null,
            'draft' => $usedDraft14 ? '14' : '13',
        ]);

        // Return the correct response format based on which proof format was used
        if ($usedDraft14) {
            // Draft 14: return "credentials" array
            return response()->json([
                'credentials' => [
                    [
                        'credential' => $credential,
                        'format' => 'vc+sd-jwt',
                    ],
                ],
                'c_nonce' => $newNonce,
                'c_nonce_expires_in' => 300,
            ]);
        }

        // Draft 13: return singular "credential"
        return response()->json([
            'format' => 'vc+sd-jwt',
            'credential' => $credential,
            'c_nonce' => $newNonce,
            'c_nonce_expires_in' => 300,
        ]);
    }

    /**
     * Extract the holder's public key JWK from the proof JWT header.
     *
     * @param  array<string, mixed>  $proofHeader
     * @return array<string, mixed>|null
     */
    private function extractHolderJwk(array $proofHeader, string $holderDid): ?array
    {
        // 1. Direct JWK in header
        if (! empty($proofHeader['jwk']) && is_array($proofHeader['jwk'])) {
            return $proofHeader['jwk'];
        }

        // 2. Decode from did:jwk
        if (str_starts_with($holderDid, 'did:jwk:')) {
            $encoded = substr($holderDid, strlen('did:jwk:'));
            $decoded = json_decode(JwtParser::base64urlDecode($encoded), true);

            if (is_array($decoded) && isset($decoded['kty'])) {
                return $decoded;
            }
        }

        return null;
    }
}
