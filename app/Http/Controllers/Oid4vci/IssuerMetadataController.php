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
                config('oid4vci.credential_type', 'BankId') => [
                    'format' => 'jwt_vc_json',
                    'scope' => 'BankId',
                    'cryptographic_binding_methods_supported' => ['did:key'],
                    'credential_signing_alg_values_supported' => ['ES256'],
                    'credential_definition' => [
                        'type' => ['VerifiableCredential', config('oid4vci.credential_type', 'BankId')],
                        'credentialSubject' => [
                            'accountId' => ['display' => [['name' => 'Account ID', 'locale' => 'en']]],
                            'IBAN' => ['display' => [['name' => 'IBAN', 'locale' => 'en']]],
                            'BIC' => ['display' => [['name' => 'BIC', 'locale' => 'en']]],
                            'givenName' => ['display' => [['name' => 'Given Name', 'locale' => 'en']]],
                            'familyName' => ['display' => [['name' => 'Family Name', 'locale' => 'en']]],
                            'birthDate' => ['display' => [['name' => 'Date of Birth', 'locale' => 'en']]],
                        ],
                    ],
                    'display' => [
                        [
                            'name' => 'Bank ID Credential',
                            'locale' => 'en',
                        ],
                    ],
                ],
            ],
            'display' => [
                [
                    'name' => 'QCC Spike Issuer',
                    'locale' => 'en',
                ],
            ],
        ]);
    }
}
