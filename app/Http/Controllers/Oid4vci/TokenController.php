<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use App\Services\Oid4vci\IssuanceSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function __invoke(Request $request, IssuanceSession $session): JsonResponse
    {
        $grantType = $request->input('grant_type');

        if ($grantType !== 'urn:ietf:params:oauth:grant-type:pre-authorized_code') {
            return response()->json([
                'error' => 'unsupported_grant_type',
            ], 400);
        }

        $code = $request->input('pre-authorized_code');

        if (empty($code)) {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'Missing pre-authorized_code',
            ], 400);
        }

        $result = $session->exchangeCode($code);

        if ($result === null) {
            return response()->json([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid or expired pre-authorized code',
            ], 400);
        }

        return response()->json([
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => config('oid4vci.token_ttl_seconds', 3600),
            'c_nonce' => $result['c_nonce'],
            'c_nonce_expires_in' => config('oid4vci.nonce_ttl_seconds', 300),
        ]);
    }
}
