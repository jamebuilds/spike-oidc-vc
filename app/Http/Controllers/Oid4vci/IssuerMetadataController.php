<?php

namespace App\Http\Controllers\Oid4vci;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class IssuerMetadataController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $issuerUrl = config('oid4vci.issuer_url');

        return response()->json([
            'credential_issuer' => $issuerUrl,
            'credential_endpoint' => $issuerUrl.'/oid4vci/credential',
            'token_endpoint' => $issuerUrl.'/oid4vci/token',
            'credential_configurations_supported' => [
                config('oid4vci.credential_type', 'AccredifyEmployeePass') => [
                    'format' => 'vc+sd-jwt',
                    'vct' => $issuerUrl.'/'.config('oid4vci.credential_type', 'AccredifyEmployeePass'),
                    'scope' => 'AccredifyEmployeePass',
                    'cryptographic_binding_methods_supported' => ['did:jwk', 'did:key'],
                    'credential_signing_alg_values_supported' => ['ES256'],
                    'proof_types_supported' => [
                        'jwt' => [
                            'proof_signing_alg_values_supported' => ['ES256'],
                        ],
                    ],
                    'display' => [
                        [
                            'name' => 'Accredify Employee Pass',
                            'locale' => 'en',
                        ],
                    ],
                ],
            ],
            'display' => [
                [
                    'name' => 'Accredify Spike Issuer',
                    'locale' => 'en',
                ],
            ],
        ]);
    }
}
