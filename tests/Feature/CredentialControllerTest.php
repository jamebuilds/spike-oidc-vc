<?php

use App\Models\Credential;
use App\Models\User;
use App\Models\WalletKey;

beforeEach(function () {
    $this->user = User::factory()->create();
    WalletKey::factory()->create(['user_id' => $this->user->id]);
    $this->credential = Credential::factory()->create(['user_id' => $this->user->id]);
});

describe('show', function () {
    it('displays the credential detail page', function () {
        $response = $this->actingAs($this->user)
            ->get("/wallet/credentials/{$this->credential->id}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('wallet/credentials/show')
            ->has('credential')
            ->where('credential.id', $this->credential->id)
            ->where('credential.type', $this->credential->type)
            ->where('credential.issuer', $this->credential->issuer)
        );
    });

    it('returns 403 for another users credential', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->get("/wallet/credentials/{$this->credential->id}");

        $response->assertForbidden();
    });
});

describe('destroy', function () {
    it('soft deletes a credential', function () {
        $response = $this->actingAs($this->user)
            ->delete("/wallet/credentials/{$this->credential->id}");

        $response->assertRedirect(route('wallet.dashboard'));
        $this->assertSoftDeleted('credentials', ['id' => $this->credential->id]);
    });

    it('returns 403 when deleting another users credential', function () {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->delete("/wallet/credentials/{$this->credential->id}");

        $response->assertForbidden();
    });
});
