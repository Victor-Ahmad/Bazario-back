<?php

namespace Tests\Feature;

use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_logout_flow(): void
    {
        Role::create(['name' => 'customer']);

        $register = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ], ['Accept-Language' => 'en']);

        $register->assertStatus(200);
        $this->assertNotEmpty($register->json('result.token'));

        $login = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ], ['Accept-Language' => 'en']);

        $login->assertStatus(200);
        $this->assertNotEmpty($login->json('result.token'));

        $user = User::where('email', 'test@example.com')->firstOrFail();
        Sanctum::actingAs($user);

        $logout = $this->postJson('/api/logout', [], ['Accept-Language' => 'en']);
        $logout->assertStatus(200);
    }

    public function test_login_rejects_invalid_password(): void
    {
        Role::create(['name' => 'customer']);
        $user = User::factory()->create([
            'email' => 'invalid@example.com',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole('customer');

        $this->postJson('/api/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrong',
        ], ['Accept-Language' => 'en'])->assertStatus(401);
    }

    public function test_password_reset_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('oldpass'),
        ]);

        $this->postJson('/api/password/forgot', [
            'email' => 'reset@example.com',
        ], ['Accept-Language' => 'en'])->assertStatus(200);

        $otp = OtpCode::where('email', 'reset@example.com')->latest()->first();
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/password/verify-otp', [
            'email' => 'reset@example.com',
            'otp' => $otp->otp,
        ], ['Accept-Language' => 'en']);

        $verify->assertStatus(200);
        $token = $verify->json('result.token');
        $this->assertNotEmpty($token);

        $reset = $this->postJson('/api/password/reset', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'newpass123',
        ], ['Accept-Language' => 'en']);

        $reset->assertStatus(200);
        $this->assertNull(
            OtpCode::where('email', 'reset@example.com')->first(),
            'OTP record should be deleted after reset.'
        );
    }

    public function test_password_reset_rejects_unknown_email(): void
    {
        $this->postJson('/api/password/forgot', [
            'email' => 'missing@example.com',
        ], ['Accept-Language' => 'en'])->assertStatus(404);
    }
}
