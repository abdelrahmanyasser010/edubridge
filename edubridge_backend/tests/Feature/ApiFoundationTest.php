<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiFoundationTest extends TestCase
{
    public function test_success_response_includes_request_id_and_locale(): void
    {
        $response = $this->getJson('/api/v1/_test/success', [
            'Accept-Language' => 'ar',
        ]);

        $requestId = $response->headers->get('X-Request-Id');

        $response
            ->assertOk()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('meta.request_id', $requestId);

        $this->assertIsString($requestId);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $requestId);
    }

    public function test_unauthenticated_response_uses_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/_test/authenticated', [
            'X-Request-Id' => 'request-auth-001',
        ]);

        $response
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'request-auth-001')
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonPath('meta.request_id', 'request-auth-001');
    }

    public function test_forbidden_response_uses_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/_test/forbidden', [
            'X-Request-Id' => 'request-forbidden-001',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('meta.request_id', 'request-forbidden-001');
    }

    public function test_not_found_response_uses_error_envelope(): void
    {
        $response = $this->getJson('/api/v1/missing-resource', [
            'X-Request-Id' => 'request-not-found-001',
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND')
            ->assertJsonPath('meta.request_id', 'request-not-found-001');
    }

    public function test_validation_response_uses_error_envelope(): void
    {
        $response = $this->postJson('/api/v1/_test/validation', [], [
            'X-Request-Id' => 'request-validation-001',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('meta.request_id', 'request-validation-001')
            ->assertJsonValidationErrors(['name']);
    }

    public function test_rate_limited_response_uses_error_envelope(): void
    {
        RateLimiter::clear('api|127.0.0.1');

        $this->getJson('/api/v1/_test/throttled', [
            'X-Request-Id' => 'request-rate-first',
        ])->assertOk();

        $response = $this->getJson('/api/v1/_test/throttled', [
            'X-Request-Id' => 'request-rate-second',
        ]);

        $response
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'RATE_LIMITED')
            ->assertJsonPath('meta.request_id', 'request-rate-second');
    }
}
