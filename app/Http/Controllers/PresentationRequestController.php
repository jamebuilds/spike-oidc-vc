<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PresentationRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('oid4vp/create');
    }

    public function store(): JsonResponse
    {
        $id = Str::uuid()->toString();
        $nonce = Str::random(32);

        $requestUri = url("/oid4vp/{$id}");
        $responseUri = url("/oid4vp/{$id}/response");
        $clientId = $requestUri;

        $requestData = [
            'response_type' => 'vp_token',
            'nonce' => $nonce,
            'client_id' => $clientId,
            'response_uri' => $responseUri,
            'response_mode' => 'direct_post',
            'dcql_query' => [
                'credentials' => [[
                    'id' => 'cred_vc',
                    'format' => 'dc+sd-jwt',
                    'meta' => ['vct_values' => ['urn:eudi:pid:1']],
                    'claims' => [['path' => ['age_equal_or_over', '18']]],
                ]],
            ],
            'client_metadata' => [
                'vp_formats' => [
                    'dc+sd-jwt' => [
                        'alg' => ['ES256'],
                    ],
                ],
            ],
        ];

        Cache::put("oid4vp:request:{$id}", $requestData, now()->addMinutes(10));
        Cache::put("oid4vp:status:{$id}", ['status' => 'pending'], now()->addMinutes(10));

        $openid4vpUri = 'openid4vp://?'.http_build_query([
            'request_uri' => $requestUri,
            'client_id' => $clientId,
        ]);

        return response()->json([
            'id' => $id,
            'uri' => $openid4vpUri,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $requestData = Cache::get("oid4vp:request:{$id}");

        if (! $requestData) {
            abort(404, 'Presentation request not found or expired.');
        }

        return response()->json($requestData);
    }
}
