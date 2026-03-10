<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CredentialController extends Controller
{
    public function show(Request $request, Credential $credential): Response
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        return Inertia::render('wallet/credentials/show', [
            'credential' => [
                'id' => $credential->id,
                'issuer' => $credential->issuer,
                'type' => $credential->type,
                'payload_claims' => $credential->payload_claims,
                'disclosure_mapping' => $credential->disclosure_mapping,
                'cnf_jwk' => $credential->cnf_jwk,
                'issued_at' => $credential->issued_at?->toISOString(),
                'expires_at' => $credential->expires_at?->toISOString(),
                'created_at' => $credential->created_at->toISOString(),
            ],
        ]);
    }

    public function destroy(Request $request, Credential $credential): RedirectResponse
    {
        abort_unless($credential->user_id === $request->user()->id, 403);

        $credential->delete();

        return redirect()->route('wallet.dashboard')
            ->with('success', 'Credential deleted.');
    }
}
