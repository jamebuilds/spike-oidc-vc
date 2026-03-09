<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
use App\Services\Oid4vp\SdJwt\SdJwtVerifier;
use App\Services\Oid4vp\VpToken\VpTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorePresentationResponseController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        SdJwtVerifier $sdJwtVerifier,
        VpTokenVerifier $vpTokenVerifier,
    ): JsonResponse {
        $requestData = Cache::get("oid4vp:request:{$id}");

        if (! $requestData) {
            abort(404, 'Presentation request not found or expired.');
        }

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

        Cache::put("oid4vp:status:{$id}", [
            'status' => 'complete',
            'data' => $responseData,
            'verification' => $verificationResult,
        ], now()->addMinutes(10));

        return response()->json(['status' => 'ok']);
    }
}
