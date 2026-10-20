<?php

namespace Tests\Feature\Muafa4;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CorsAndRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function preflight(string $url, string $origin)
    {
        return $this->call('OPTIONS', $url, [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);
    }

    public function test_every_api_route_gets_cors_for_muafa_sites_only(): void
    {
        $urls = ['/api/refresh', '/api/hospital-types', '/api/password/forgot', '/api/fcm-token', '/api/my-rates',
                 '/api/account', '/api/provinces', '/api/hospital/login', '/api/admin/dues', '/api/user/login', '/api/nurse/login'];

        foreach (['https://admin.muafa-sy.com', 'https://muafa-sy.com', 'https://www.muafa-sy.com', 'https://hospital.muafa-sy.com'] as $origin) {
            foreach ($urls as $url) {
                $this->assertSame($origin, $this->preflight($url, $origin)->headers->get('Access-Control-Allow-Origin'), "$origin → $url");
            }
        }

        foreach (['https://evil.example.com', 'https://muafa-sy.com.evil.com', 'https://notmuafa-sy.com'] as $origin) {
            $this->assertNull($this->preflight('/api/refresh', $origin)->headers->get('Access-Control-Allow-Origin'), "should be blocked: $origin");
        }
    }

    public function test_refresh_returns_token_key_and_accepts_body_token(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'api']);
        $acc = Account::create(['email' => 'd@x.com', 'password' => 'x', 'phone_number' => '1', 'is_approved' => 'approved']);

        Carbon::setTestNow('2026-10-21 10:00:00');
        $token = JWTAuth::fromUser($acc);
        Carbon::setTestNow('2026-10-21 10:30:00'); // expired (15 min TTL)

        $r = $this->withHeader('Authorization', 'Bearer ' . $token)->getJson('/api/refresh')->assertOk();
        $this->assertSame($r->json('token'), $r->json('access_token'));

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $token2 = JWTAuth::fromUser($acc);
        $this->getJson('/api/refresh?token=' . $token2)->assertOk()->assertJsonStructure(['token']);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/refresh')->assertStatus(401)->assertJson(['error' => 'Token not provided']);
    }
}
