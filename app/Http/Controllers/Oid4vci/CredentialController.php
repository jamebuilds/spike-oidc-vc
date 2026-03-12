<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\CredentialSigner;
use App\Services\Oid4vci\IssuanceSession;
use App\Services\Oid4vp\SdJwt\JwtParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(
        private IssuanceSession $session,
        private CredentialSigner $signer,
    ) {}

    public function __invoke(Request $request): JsonResponse
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

        // Sign and issue the credential
        $credential = $this->signer->sign($offer['subject_claims'], $holderDid);

        // Mark issuance as complete
        $this->session->complete($tokenData['offer_id'], [
            'holder_did' => $holderDid,
            'credential_type' => config('oid4vci.credential_type', 'BankId'),
        ]);

        return response()->json([
            'format' => 'jwt_vc_json',
            'credential' => $credential,
        ]);
    }
}
