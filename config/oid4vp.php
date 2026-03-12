<?php

return [
    'skip_signature_verification' => env('SDJWT_SKIP_SIGNATURE_VERIFY', false),
    'issuer_jwks_uri' => env('SDJWT_ISSUER_JWKS_URI'),
    'issuer_public_key' => env('SDJWT_ISSUER_PUBLIC_KEY'),
];
