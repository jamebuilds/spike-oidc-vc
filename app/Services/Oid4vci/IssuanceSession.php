<?php

namespace App\Services\Oid4vci;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IssuanceSession
{
    private const TTL_MINUTES = 10;

    /**
     * Create a new credential offer session.
     *
     * @param  array<string, mixed>  $subjectClaims
     */
    public function create(string $id, array $subjectClaims): string
    {
        $ttl = now()->addMinutes(self::TTL_MINUTES);
        $preAuthorizedCode = Str::random(64);

        Cache::put("oid4vci:offer:{$id}", [
            'id' => $id,
            'pre_authorized_code' => $preAuthorizedCode,
            'subject_claims' => $subjectClaims,
        ], $ttl);

        Cache::put("oid4vci:code:{$preAuthorizedCode}", $id, $ttl);
        Cache::put("oid4vci:status:{$id}", ['status' => 'pending'], $ttl);

        return $preAuthorizedCode;
    }

    public function find(string $id): ?array
    {
        return Cache::get("oid4vci:offer:{$id}");
    }

    public function findOrFail(string $id): array
    {
        return $this->find($id) ?? abort(404, 'Credential offer not found or expired.');
    }

    /**
     * Look up an offer by its pre-authorized code.
     */
    public function findByCode(string $code): ?array
    {
        $offerId = Cache::get("oid4vci:code:{$code}");

        if ($offerId === null) {
            return null;
        }

        return $this->find($offerId);
    }

    /**
     * Exchange a pre-authorized code for an access token + c_nonce.
     * The code is single-use and deleted after exchange.
     *
     * @return array{access_token: string, c_nonce: string, offer_id: string}|null
     */
    public function exchangeCode(string $code): ?array
    {
        $offerId = Cache::pull("oid4vci:code:{$code}");

        if ($offerId === null) {
            return null;
        }

        $accessToken = Str::random(64);
        $cNonce = Str::random(32);
        $ttl = now()->addSeconds(config('oid4vci.token_ttl_seconds', 3600));

        Cache::put("oid4vci:token:{$accessToken}", [
            'offer_id' => $offerId,
            'c_nonce' => $cNonce,
        ], $ttl);

        return [
            'access_token' => $accessToken,
            'c_nonce' => $cNonce,
            'offer_id' => $offerId,
        ];
    }

    /**
     * Look up session data by access token.
     *
     * @return array{offer_id: string, c_nonce: string}|null
     */
    public function findByAccessToken(string $accessToken): ?array
    {
        return Cache::get("oid4vci:token:{$accessToken}");
    }

    public function getStatus(string $id): array
    {
        return Cache::get("oid4vci:status:{$id}", ['status' => 'pending']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(string $id, array $data): void
    {
        Cache::put("oid4vci:status:{$id}", [
            'status' => 'complete',
            'data' => $data,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Build the openid-credential-offer:// URI for wallet scanning.
     */
    public function buildCredentialOfferUri(string $id): string
    {
        $offerUri = url("/oid4vci/{$id}/offer");

        return 'openid-credential-offer://?credential_offer_uri='.urlencode($offerUri);
    }
}
