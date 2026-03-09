<?php

namespace App\Http\Controllers\Oid4vp;

use App\Http\Controllers\Controller;
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

        $responseUri = url("/oid4vp/{$id}/response");
        $clientId = $responseUri;
        $pdUri = url("/oid4vp/{$id}/pd");

        $presentationDefinition = [
            'id' => $id,
            'input_descriptors' => [[
                'id' => 'identity_credential',
                'format' => [
                    'vc+sd-jwt' => ['alg' => ['ES256']],
                ],
                'constraints' => [
                    'fields' => [
                        [
                            'path' => ['$.vct'],
                            'filter' => ['type' => 'string', 'const' => 'urn:eudi:pid:1'],
                        ],
                        [
                            'path' => ['$.age_equal_or_over.18'],
                        ],
                    ],
                ],
            ]],
        ];

        Cache::put("oid4vp:request:{$id}", [
            'nonce' => $nonce,
            'presentation_definition' => $presentationDefinition,
        ], now()->addMinutes(10));
        Cache::put("oid4vp:status:{$id}", ['status' => 'pending'], now()->addMinutes(10));

        $openid4vpUri = 'openid4vp://authorize?'.http_build_query([
            'response_type' => 'vp_token',
            'client_id' => $clientId,
            'response_mode' => 'direct_post',
            'state' => $id,
            'presentation_definition_uri' => $pdUri,
            'client_id_scheme' => 'redirect_uri',
            'nonce' => $nonce,
            'response_uri' => $responseUri,
        ]);

        return response()->json([
            'id' => $id,
            'uri' => $openid4vpUri,
        ]);
    }

    public function presentationDefinition(string $id): JsonResponse
    {
        $requestData = Cache::get("oid4vp:request:{$id}");

        if (! $requestData) {
            abort(404, 'Presentation request not found or expired.');
        }

        return response()->json($requestData['presentation_definition']);
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
