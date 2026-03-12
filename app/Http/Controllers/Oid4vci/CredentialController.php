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
            'content_type' => $request->header('Content-Type'),
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

        // Validate proof JWT
        $proof = $request->input('proof');

        if (empty($proof) || ($proof['proof_type'] ?? null) !== 'jwt') {
            return response()->json([
                'error' => 'invalid_proof',
                'error_description' => 'Missing or invalid proof. Expected proof_type=jwt.',
            ], 400);
        }

        $proofJwt = $proof['jwt'] ?? null;

        if (empty($proofJwt)) {
            return response()->json([
                'error' => 'invalid_proof',
                'error_description' => 'Missing proof JWT',
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

        Log::info('OID4VCI Credential issued', [
            'holder_did' => $holderDid,
            'has_cnf' => $holderJwk !== null,
        ]);

        return response()->json([
            'format' => 'vc+sd-jwt',
            'credential' => $credential,
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
