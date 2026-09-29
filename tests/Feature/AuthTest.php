<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;
use Illuminate\Http\Request;
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berhasil(): void
    {
        $user = User::factory()->create([
            'email' => 'bryan@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'bryan@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        $this->assertNotEmpty(
            $response->json('data.token')
        );
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        User::factory()->create([
            'email' => 'bryan@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'bryan@example.com',
            'password' => 'password-salah',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_endpoint_protected_tanpa_token_ditolak(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_logout_membuat_token_tidak_dapat_digunakan_lagi(): void
    {
        $user = User::factory()->create([
            'email' => 'bryan@example.com',
            'password' => 'password123',
        ]);

        // Login
        $loginResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200);

        $token = $loginResponse->json('data.token');

        $this->assertNotEmpty($token);

        // Token harus ada
        $this->assertNotNull(
            \Laravel\Sanctum\PersonalAccessToken::findToken($token)
        );

        // Logout
        $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertStatus(200)
            ->assertJson([
                'message' => 'Logout successful',
            ]);

        // Token harus sudah dihapus
        $this->assertNull(
            \Laravel\Sanctum\PersonalAccessToken::findToken($token)
        );

        /*
        * Request logout dan request setelah logout
        * harus menggunakan application lifecycle yang berbeda.
        */
        $this->refreshApplication();

        // Token yang sudah dihapus harus ditolak
        $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me')
            ->assertStatus(401);
    }
}