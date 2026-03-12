<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\IssuanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TokenController extends Controller
{
    public function __invoke(Request $request, IssuanceSession $session): JsonResponse
    {
        Log::info('OID4VCI Token request', [
            'grant_type' => $request->input('grant_type'),
            'has_code' => ! empty($request->input('pre-authorized_code')),
        ]);

        $grantType = $request->input('grant_type');

        if ($grantType !== 'urn:ietf:params:oauth:grant-type:pre-authorized_code') {
            Log::warning('OID4VCI Token: unsupported grant type', ['grant_type' => $grantType]);

            return response()->json([
                'error' => 'unsupported_grant_type',
            ], 400);
        }

        $code = $request->input('pre-authorized_code');

        if (empty($code)) {
            Log::warning('OID4VCI Token: missing pre-authorized_code');

            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing pre-authorized_code',
            ], 400);
        }

        $result = $session->exchangeCode($code);

        if ($result === null) {
            Log::warning('OID4VCI Token: invalid or expired code', ['code_prefix' => substr($code, 0, 8).'...']);

            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid or expired pre-authorized code',
            ], 400);
        }

        Log::info('OID4VCI Token: success', ['offer_id' => $result['offer_id']]);

        return response()->json([
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => config('oid4vci.token_ttl_seconds', 3600),
            'c_nonce' => $result['c_nonce'],
            'c_nonce_expires_in' => config('oid4vci.nonce_ttl_seconds', 300),
        ]);
    }
}
