<?php

return [
    'issuer_url' => env('OID4VCI_ISSUER_URL', env('APP_URL')),
    'signing_key_pem' => env('OID4VCI_SIGNING_KEY_PEM'),
    'signing_key_id' => env('OID4VCI_SIGNING_KEY_ID', 'key-1'),
    'credential_type' => 'AccredifyEmployeePass',
    'token_ttl_seconds' => 3600,
    'nonce_ttl_seconds' => 300,
];
