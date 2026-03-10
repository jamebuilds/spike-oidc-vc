<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $credentials = $request->user()
            ->credentials()
            ->latest()
            ->get()
            ->map(fn ($credential) => [
                'id' => $credential->id,
                'issuer' => $credential->issuer,
                'type' => $credential->type,
                'payload_claims' => $credential->payload_claims,
                'disclosure_mapping' => $credential->disclosure_mapping,
                'issued_at' => $credential->issued_at?->toISOString(),
                'expires_at' => $credential->expires_at?->toISOString(),
            ]);

        return Inertia::render('wallet/index', [
            'credentials' => $credentials,
        ]);
    }
}
