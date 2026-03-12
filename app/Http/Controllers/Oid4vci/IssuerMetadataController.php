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
                    'scope' => 'AccredifyEmployeePass',
                    'cryptographic_binding_methods_supported' => ['did:jwk', 'did:key'],
                    'credential_signing_alg_values_supported' => ['ES256'],
                    'credential_definition' => [
                        'type' => ['VerifiableCredential', config('oid4vci.credential_type', 'AccredifyEmployeePass')],
                        'credentialSubject' => [
                            'employeeId' => ['display' => [['name' => 'Employee ID', 'locale' => 'en']]],
                            'firstName' => ['display' => [['name' => 'First Name', 'locale' => 'en']]],
                            'lastName' => ['display' => [['name' => 'Last Name', 'locale' => 'en']]],
                            'dateOfBirth' => ['display' => [['name' => 'Date of Birth', 'locale' => 'en']]],
                            'nric' => ['display' => [['name' => 'NRIC', 'locale' => 'en']]],
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
