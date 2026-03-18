# Spike: Verifiable Credentials with Google QCC + Digital Credentials API

Proof-of-concept Laravel application exploring three approaches to issuing and verifying digital credentials using SD-JWT:

1. **OID4VCI** — Issue credentials to a wallet via QR code
2. **OID4VP** — Request credential presentation via QR code
3. **DC API** — Request credentials via the browser's Digital Credentials API (no QR code)

Built to validate the end-to-end flow before production implementation. Pairs with the [spikeMobileWallet](../spikeMobileWallet) Android app.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  Frontend (React + Inertia)                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ OID4VCI      │  │ OID4VP       │  │ DC API                 │ │
│  │ Issue cred   │  │ QR present   │  │ Browser-native present │ │
│  │ → QR code    │  │ → QR code    │  │ → credentials.get()    │ │
│  └──────┬───────┘  └──────┬───────┘  └────────────┬───────────┘ │
└─────────┼─────────────────┼───────────────────────┼─────────────┘
          │                 │                       │
┌─────────┼─────────────────┼───────────────────────┼─────────────┐
│  Backend (Laravel)        │                       │             │
│  ┌──────┴───────┐  ┌──────┴───────┐  ┌────────────┴───────────┐ │
│  │ Issuance     │  │ Presentation │  │ DC API                 │ │
│  │ Controllers  │  │ Controllers  │  │ Controllers            │ │
│  └──────┬───────┘  └──────┬───────┘  └────────────┬───────────┘ │
│         │                 │                       │             │
│  ┌──────┴───────┐  ┌──────┴───────────────────────┴───────────┐ │
│  │ Credential   │  │ Session Management (Cache, 10-min TTL)   │ │
│  │ Signer       │  ├─────────────────────────────────────────┤ │
│  │ (ES256)      │  │ SD-JWT Verifier  │  VP Token Verifier   │ │
│  └──────────────┘  └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
          │                 │                       │
          ▼                 ▼                       ▼
   ┌─────────────────────────────────────────────────────────────┐
   │  Mobile Wallet (spikeMobileWallet)                          │
   │  - Receives credentials via OID4VCI                         │
   │  - Presents credentials via OID4VP (direct_post)            │
   │  - Presents credentials via DC API (Credential Manager)     │
   └─────────────────────────────────────────────────────────────┘
```

## Design Decisions

### Why three separate flows?

- **OID4VCI + OID4VP** are the standard OpenID4VC protocols. They work cross-device via QR codes and are wallet-agnostic.
- **DC API** is Google's browser-native approach (`navigator.credentials.get()`). It removes the QR scan step — the browser talks directly to the wallet via Android's Credential Manager. This is the path Google is pushing for web-to-wallet interactions.

We implemented all three to understand the differences and validate that one backend can serve both interaction models.

### SD-JWT format split: `vc+sd-jwt` vs `dc+sd-jwt`

The QR-based OID4VP flow uses `vc+sd-jwt` (the IETF standard). The DC API flow uses `dc+sd-jwt` (Google's Digital Credentials profile). These are structurally identical — the difference is in the MIME type the wallet uses to match credentials. The backend verifier (`SdJwtVerifier`) handles both identically.

**Production note:** The credential type in the wallet registration must match what the verifier requests. If you issue as `vc+sd-jwt` but request `dc+sd-jwt`, the Credential Manager won't find a match.

### Query format split: `presentation_definition` vs `dcql_query`

- **OID4VP (QR)** uses `presentation_definition` with JSONPath selectors (`$.employeeId`) and `filter.pattern` for VCT matching. This is the OpenID4VP Draft 20 format.
- **DC API** uses `dcql_query` with simple path arrays (`["employeeId"]`) and `meta.vct_values` for VCT matching. This is the DCQL format from OpenID4VP Draft 24+.

The DC API controller builds its own DCQL query inline rather than using `PresentationSession::buildPresentationDefinition()`. This keeps the two query formats independent.

### Stateless session management via cache

All session state (nonces, presentation definitions, verification results) is stored in Laravel's cache with a 10-minute TTL. No database tables needed. Sessions are keyed by UUID.

This works for the spike but production should use a persistent store with proper cleanup, since cache eviction could drop an in-flight verification.

### Issuer identity: `did:jwk` vs HTTPS URL

The OID4VCI flow issues credentials with `did:jwk:<base64url(JWK)>` as the issuer. The public key is embedded in the DID itself, so the verifier can extract it without any network call.

The test wallet uses `https://accredify.example.com` as the issuer for its built-in test credentials. The verifier gracefully handles this by reporting "could not resolve issuer public key" rather than crashing — signature verification is skipped but disclosure processing continues.

**Production note:** For HTTPS issuers, implement key resolution via `{issuer}/.well-known/jwt-issuer` or a pre-configured trust list.

### Holder key binding: DID vs JWK

The credential signer supports two binding modes:

- **DID binding** (`cnf.kid`) — used when the wallet provides a `did:key` or `did:jwk` in the proof JWT. The verifier resolves the key from the DID.
- **JWK binding** (`cnf.jwk`) — used when the wallet sends a raw JWK (e.g., from Android Keystore). The public key is embedded directly in the credential.

The signer auto-detects which mode to use based on what the wallet sends in its proof JWT header.

### CSRF exemptions

Endpoints called by external wallets (not browser forms) are exempt from CSRF:

- `oid4vp/*/response` — wallet POSTs the VP token here after scanning QR
- `oid4vci/token` — wallet exchanges pre-authorized code
- `oid4vci/credential` — wallet requests signed credential

DC API verification (`dc-api/*/verify`) is NOT exempt — it's called by our own frontend via `fetch()` with the XSRF token cookie.

## Flows

### OID4VCI: Issue a Credential

```
Browser                      Backend                         Wallet
  │                            │                               │
  │  POST /oid4vci             │                               │
  │──────────────────────────>│                               │
  │  QR code (openid-         │                               │
  │  credential-offer://...)  │                               │
  │<──────────────────────────│                               │
  │                            │   GET /oid4vci/{id}/offer     │
  │                            │<──────────────────────────────│
  │                            │   offer metadata              │
  │                            │──────────────────────────────>│
  │                            │                               │
  │                            │   POST /oid4vci/token         │
  │                            │<──────────────────────────────│
  │                            │   access_token + c_nonce      │
  │                            │──────────────────────────────>│
  │                            │                               │
  │                            │   POST /oid4vci/credential    │
  │                            │<──────────────────────────────│
  │                            │   signed SD-JWT (vc+sd-jwt)   │
  │                            │──────────────────────────────>│
  │                            │                               │
  │  poll /oid4vci/{id}/status │                               │
  │──────────────────────────>│                               │
  │  status: complete          │                               │
  │<──────────────────────────│                               │
```

**Credential issued:** `AccredifyEmployeePass` with selectively-disclosable claims (employeeId, firstName, lastName, dateOfBirth, nric).

### OID4VP: Present via QR Code

```
Browser                      Backend                         Wallet
  │                            │                               │
  │  POST /oid4vp              │                               │
  │──────────────────────────>│                               │
  │  QR code (openid4vp://    │                               │
  │  authorize?...)           │                               │
  │<──────────────────────────│                               │
  │                            │   GET /oid4vp/{id}/pd         │
  │                            │<──────────────────────────────│
  │                            │   presentation_definition     │
  │                            │──────────────────────────────>│
  │                            │                               │
  │                            │   POST /oid4vp/{id}/response  │
  │                            │<──────────────────────────────│
  │                            │   vp_token + pres_submission  │
  │                            │──────────────────────────────>│ (direct_post)
  │                            │                               │
  │  poll /oid4vp/{id}/status  │                               │
  │──────────────────────────>│                               │
  │  verification result       │                               │
  │<──────────────────────────│                               │
```

**Query format:** `presentation_definition` with `vc+sd-jwt`, JSONPath claim paths, `filter.pattern` for VCT.

### DC API: Present via Browser

```
Browser                      Backend                Credential Manager / Wallet
  │                            │                               │
  │  POST /dc-api              │                               │
  │──────────────────────────>│                               │
  │  { nonce, dcql_query }     │                               │
  │<──────────────────────────│                               │
  │                            │                               │
  │  navigator.credentials.get({                               │
  │    digital: {                                              │
  │      requests: [{                                          │
  │        protocol: "openid4vp-v1-unsigned",                  │
  │        data: { response_type, nonce, dcql_query }          │
  │      }]                                                    │
  │    }                                                       │
  │  })                        │                               │
  │────────────────────────────────────────────────────────────>│
  │                            │                  wallet picker │
  │                            │                  user confirms │
  │  { protocol, data: "eyJ..." }                              │
  │<────────────────────────────────────────────────────────────│
  │                            │                               │
  │  POST /dc-api/{id}/verify  │                               │
  │──────────────────────────>│                               │
  │  verification result       │                               │
  │<──────────────────────────│                               │
```

**Query format:** `dcql_query` with `dc+sd-jwt`, simple claim paths (`["employeeId"]`), `meta.vct_values` for VCT.

## Gotchas & Lessons Learned

These are the critical findings from this spike. **Read these before production implementation.**

### DC API request format

| Detail | Wrong (what we tried first) | Correct |
|---|---|---|
| API field | `providers` | `requests` |
| Data type | `JSON.stringify(data)` | Plain JS object |
| Query format | `presentation_definition` | `dcql_query` |
| SD-JWT format | `vc+sd-jwt` | `dc+sd-jwt` |
| Claim paths | `["$.employeeId"]` | `["employeeId"]` |
| VCT matching | `filter: { pattern: "..." }` | `meta: { vct_values: [...] }` |

### SD-JWT signing

- OpenSSL produces DER-encoded ECDSA signatures. JWS requires raw R\|\|S (64 bytes for P-256). Must convert manually — see `CredentialSigner::derToRaw()`.
- EC key coordinates (`x`, `y`) must be zero-padded to 32 bytes before base64url encoding.
- PEM keys in `.env` need `\n` literal escaping (stored as single line, converted at runtime).

### Wallet credential registration

- The wallet must register credentials with Android's Credential Manager using a WASM matcher (`openid4vp1_0.wasm`) for DC API matching to work.
- The `format` field in the credential registration must match what the verifier requests (`dc+sd-jwt` for DC API, `vc+sd-jwt` for OID4VP).
- If the Credential Manager can't find a matching credential, Chrome silently does nothing — no error, no dialog.

### VCT (Verifiable Credential Type) metadata

- Walt.id wallet resolves VCT metadata from `/.well-known/vct/{type}` — we serve it at both `/{type}` and `/.well-known/vct/{type}`.
- The `vct` field in the issued credential should be a URL (e.g., `https://issuer.example.com/AccredifyEmployeePass`), not just the type name.

### CSRF and cross-origin

- Wallet-to-server endpoints (OID4VP response, OID4VCI token/credential) must be CSRF-exempt.
- DC API browser-to-server endpoints can use CSRF since they're same-origin `fetch()` calls.

### Nonce verification

- OID4VP: nonce is verified from the Key Binding JWT (`kb_jwt`) appended to the SD-JWT.
- DC API: same mechanism, but test wallets may not include a KB-JWT — the verifier should handle this gracefully.

## Project Structure

```
routes/
├── web.php              # Home page
├── oid4vci.php          # Issuance endpoints + .well-known metadata
├── oid4vp.php           # QR presentation endpoints
└── dc-api.php           # Browser DC API endpoints

app/Http/Controllers/
├── Oid4vci/             # 7 controllers (offer, token, credential, metadata)
├── Oid4vp/              # 4 controllers (request, definition, response, status)
└── DcApi/               # 2 controllers (request, verify)

app/Services/
├── Oid4vci/
│   ├── IssuanceSession.php      # Cache-based session for issuance flow
│   └── CredentialSigner.php     # ES256 SD-JWT signing
└── Oid4vp/
    ├── PresentationSession.php  # Cache-based session for presentation flows
    └── SdJwt/
        ├── SdJwtVerifier.php        # Full SD-JWT verification pipeline
        ├── DisclosureProcessor.php  # Split, decode, hash-match disclosures
        ├── JwtParser.php            # JWT parsing, ES256 verify, JWK-to-PEM
        └── VerificationResult.php   # Structured verification output

resources/js/pages/
├── welcome.tsx          # Home with links to all flows
├── oid4vci/create.tsx   # Issue credential (QR + polling)
├── oid4vp/create.tsx    # Request presentation (QR + polling)
└── dc-api/create.tsx    # DC API request (browser-native)
```

## Setup

```bash
cp .env.example .env
php artisan key:generate

# Generate an EC P-256 signing key for credential issuance
openssl ecparam -genkey -name prime256v1 -noout | openssl ec -text

# Add the PEM key to .env (escape newlines)
# OID4VCI_SIGNING_KEY_PEM="-----BEGIN EC PRIVATE KEY-----\nMHQC....\n-----END EC PRIVATE KEY-----"

npm install
composer install
php artisan migrate

# Run the app
composer run dev
```

### Environment Variables

| Variable | Required | Description |
|---|---|---|
| `OID4VCI_SIGNING_KEY_PEM` | Yes (for issuance) | EC P-256 private key in PEM format, newlines escaped as `\n` |
| `OID4VCI_ISSUER_URL` | No | Issuer URL, defaults to `APP_URL` |
| `SDJWT_SKIP_SIGNATURE_VERIFY` | No | Set `true` to skip signature verification (testing only) |

### Testing the DC API

Requires Chrome 141+ on Android with the Digital Credentials API flag enabled:

1. Enable `chrome://flags/#web-identity-digital-credentials`
2. Install the [spikeMobileWallet](../spikeMobileWallet) app with a registered credential
3. Open the verifier app's `/dc-api/create` page on the same device
4. Tap "Request Credential" — the Android credential selector should appear

## Tech Stack

- **Backend:** PHP 8.4, Laravel 12, Inertia.js v2
- **Frontend:** React 19, TypeScript, Tailwind CSS v4
- **Crypto:** OpenSSL (ES256), SHA-256, base64url
- **Session:** Laravel Cache (database-backed, 10-min TTL)
- **Bundler:** Vite
