<?php

namespace Tests\Feature;

use App\Models\ConnectAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConnectOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStripeClient()
    {
        return new class extends \Stripe\StripeClient {
            public $accounts;
            public $accountLinks;

            public function __construct()
            {
                $this->accounts = new class {
                    public function create(array $params)
                    {
                        return (object) [
                            'id' => 'acct_test_123',
                            'charges_enabled' => false,
                            'payouts_enabled' => false,
                            'details_submitted' => false,
                            'requirements' => [],
                        ];
                    }
                };

                $this->accountLinks = new class {
                    public function create(array $params)
                    {
                        return (object) [
                            'url' => 'https://connect.stripe.test/onboard',
                            'expires_at' => now()->addHour()->timestamp,
                        ];
                    }
                };
            }
        };
    }

    public function test_customer_cannot_start_connect_onboarding(): void
    {
        Role::create(['name' => 'customer']);

        $user = User::factory()->create();
        $user->assignRole('customer');
        Sanctum::actingAs($user);

        $this->postJson('/api/connect/onboard')->assertStatus(403);
    }

    public function test_seller_can_start_connect_onboarding_and_persists_account(): void
    {
        Role::create(['name' => 'seller']);

        $user = User::factory()->create();
        $user->assignRole('seller');

        $this->app->instance(\Stripe\StripeClient::class, $this->fakeStripeClient());

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connect/onboard');
        $response->assertStatus(200)
            ->assertJsonStructure(['onboarding_url', 'expires_at', 'account']);

        $this->assertDatabaseHas('connect_accounts', [
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_test_123',
        ]);
    }

    public function test_connect_status_returns_connected_true_when_account_exists(): void
    {
        Role::create(['name' => 'seller']);

        $user = User::factory()->create();
        $user->assignRole('seller');

        ConnectAccount::create([
            'user_id' => $user->id,
            'stripe_account_id' => 'acct_existing_1',
            'type' => 'express',
            'charges_enabled' => true,
            'payouts_enabled' => true,
            'details_submitted' => true,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/connect/status')
            ->assertStatus(200)
            ->assertJson([
                'connected' => true,
            ]);
    }
}
