<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\PresentationSession;
use App\Services\Oid4vp\SdJwt\SdJwtVerifier;
use App\Services\Oid4vp\VpToken\VpTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorePresentationResponseController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        PresentationSession $session,
        SdJwtVerifier $sdJwtVerifier,
        VpTokenVerifier $vpTokenVerifier,
    ): JsonResponse {
        $requestData = $session->findOrFail($id);

        $responseData = $request->all();

        $verificationResult = null;
        $vpToken = $request->input('vp_token');

        if ($vpToken) {
            $expectedNonce = $requestData['nonce'] ?? '';

            // SD-JWT contains ~ delimiters; regular JWT does not
            $verifier = str_contains($vpToken, '~')
                ? $sdJwtVerifier
                : $vpTokenVerifier;

            $result = $verifier->verify($vpToken, $expectedNonce);
            $verificationResult = $result->toArray();
        }

        $session->complete($id, $responseData, $verificationResult);

        return response()->json(['status' => 'ok']);
    }
}
