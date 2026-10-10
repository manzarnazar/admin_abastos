<?php

namespace Tests\Feature\Diablero;

class DiableroAuthTest extends DiableroTestCase
{
    public function test_login_issues_token_and_protects_endpoints()
    {
        $diablero = $this->makeDiablero(['auth_token' => null, 'active' => 0]);

        $this->postJson('/api/v1/diablero/auth/login', ['phone' => $diablero->phone, 'password' => 'wrong-pass'])->assertStatus(401);

        $res = $this->postJson('/api/v1/diablero/auth/login', ['phone' => $diablero->phone, 'password' => 'secret123'])
            ->assertOk()->assertJsonPath('topic', 'diablero_zone_' . self::ZONE_ID);
        $h = ['Authorization' => 'Bearer ' . $res->json('token')];

        $this->getJson('/api/v1/diablero/profile', $h)->assertOk()->assertJsonPath('id', $diablero->id)->assertJsonMissingPath('auth_token');
        $this->getJson('/api/v1/diablero/profile?token=' . $res->json('token'))->assertOk();
        $this->postJson('/api/v1/diablero/update-active-status', [], $h)->assertOk()->assertJsonPath('active', 1);
        foreach (['tasks/available', 'tasks/current', 'tasks/history', 'earnings', 'notifications', 'wallet-transactions'] as $uri) {
            $this->getJson('/api/v1/diablero/' . $uri, $h)->assertOk();
        }
        $this->getJson('/api/v1/diablero/profile')->assertStatus(401);
        $this->getJson('/api/v1/diablero/profile', ['Authorization' => 'Bearer nope'])->assertStatus(401);

        $this->postJson('/api/v1/diablero/auth/logout', [], $h)->assertOk();
        $this->getJson('/api/v1/diablero/profile', $h)->assertStatus(401);
    }

    public function test_unapproved_or_suspended_accounts_are_refused()
    {
        $pending = $this->makeDiablero(['application_status' => 'pending']);
        $this->postJson('/api/v1/diablero/auth/login', ['phone' => $pending->phone, 'password' => 'secret123'])->assertStatus(401);
        $this->getJson('/api/v1/diablero/profile', $this->diableroHeaders($pending))->assertStatus(401);

        $suspended = $this->makeDiablero(['status' => 0]);
        $this->postJson('/api/v1/diablero/auth/login', ['phone' => $suspended->phone, 'password' => 'secret123'])->assertStatus(401);
        $this->getJson('/api/v1/diablero/profile', $this->diableroHeaders($suspended))->assertStatus(401);
    }
}
