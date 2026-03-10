<?php

namespace App\Services\Oid4vp;

use Illuminate\Support\Facades\Cache;

class PresentationSession
{
    private const TTL_MINUTES = 10;

    public function create(string $id, string $nonce, array $presentationDefinition): void
    {
        $ttl = now()->addMinutes(self::TTL_MINUTES);

        Cache::put("oid4vp:request:{$id}", [
            'nonce' => $nonce,
            'presentation_definition' => $presentationDefinition,
        ], $ttl);

        Cache::put("oid4vp:status:{$id}", ['status' => 'pending'], $ttl);
    }

    public function find(string $id): ?array
    {
        return Cache::get("oid4vp:request:{$id}");
    }

    public function findOrFail(string $id): array
    {
        return $this->find($id) ?? abort(404, 'Presentation request not found or expired.');
    }

    public function getStatus(string $id): array
    {
        return Cache::get("oid4vp:status:{$id}", ['status' => 'pending']);
    }

    public function complete(string $id, array $data, ?array $verification): void
    {
        Cache::put("oid4vp:status:{$id}", [
            'status' => 'complete',
            'data' => $data,
            'verification' => $verification,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    public function buildPresentationDefinition(string $id): array
    {
        return [
            'id' => $id,
            'input_descriptors' => [[
                'id' => 'bankid_credential',
                'format' => [
                    'vc+sd-jwt' => [],
                    'jwt_vp_json' => [],
                ],
                'constraints' => [
                    'fields' => [
                        [
                            'path' => ['$.vc.type', '$.type'],
                            'filter' => [
                                'type' => 'string',
                                'pattern' => 'BankId',
                            ],
                        ],
                        ['path' => ['$.vc.credentialSubject.accountId', '$.credentialSubject.accountId']],
                        ['path' => ['$.vc.credentialSubject.IBAN', '$.credentialSubject.IBAN']],
                        ['path' => ['$.vc.credentialSubject.BIC', '$.credentialSubject.BIC']],
                        ['path' => ['$.vc.credentialSubject.givenName', '$.credentialSubject.givenName']],
                        ['path' => ['$.vc.credentialSubject.familyName', '$.credentialSubject.familyName']],
                        ['path' => ['$.vc.credentialSubject.birthDate', '$.credentialSubject.birthDate']],
                    ],
                ],
            ]],
        ];
    }

    public function buildAuthorizationUri(string $id, string $nonce): string
    {
        $responseUri = url("/oid4vp/{$id}/response");
        $pdUri = url("/oid4vp/{$id}/pd");

        return 'openid4vp://authorize?'.http_build_query([
            'response_type' => 'vp_token',
            'client_id' => $responseUri,
            'response_mode' => 'direct_post',
            'state' => $id,
            'presentation_definition_uri' => $pdUri,
            'client_id_scheme' => 'redirect_uri',
            'nonce' => $nonce,
            'response_uri' => $responseUri,
        ]);
    }
}
