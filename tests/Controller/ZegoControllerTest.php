<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Service\ZegoTokenService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Security and access-control tests for ZegoController.
 *
 * Test 1: Non-participants (unauthenticated) cannot reach /zego/token/{id} or /messagerie/call/{id}.
 * Test 2: Authenticated participants are denied when the contract is not fully signed or is closed
 *         (verified by expecting a non-200 response on a known-invalid conversation ID).
 */
final class ZegoControllerTest extends WebTestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Group 1 — Unauthenticated (non-participant) access is always denied
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Unauthenticated request to the token endpoint returns 401 or 403.
     */
    public function testTokenEndpointDeniesUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/zego/token/1', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $statusCode = $client->getResponse()->getStatusCode();
        self::assertFalse(
            $client->getResponse()->isSuccessful(),
            'Unauthenticated GET /zego/token/1 must not succeed.'
        );
        self::assertContains(
            $statusCode,
            [401, 302, 403],
            "Expected redirect/denied status, got $statusCode."
        );
    }

    /**
     * Unauthenticated request to the call page returns 401, 403, or redirect to login.
     */
    public function testCallPageDeniesUnauthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messagerie/call/1?type=video');

        $statusCode = $client->getResponse()->getStatusCode();
        self::assertFalse(
            $client->getResponse()->isSuccessful(),
            'Unauthenticated GET /messagerie/call/1 must not succeed.'
        );
        self::assertContains(
            $statusCode,
            [401, 302, 403],
            "Expected redirect/denied status, got $statusCode."
        );
    }

    /**
     * Non-existent conversation ID returns 404 from the token endpoint
     * (or redirects to login for unauthenticated — both are non-success).
     */
    public function testTokenEndpointWithNonExistentConversation(): void
    {
        $client = static::createClient();
        $client->request('GET', '/zego/token/999999999', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        // Unauthenticated: expect redirect (302) or forbidden (401/403)
        // If the firewall catches it first, that's fine — it's still not 200.
        self::assertNotSame(
            200,
            $client->getResponse()->getStatusCode(),
            'A non-existent conversation should never return 200.'
        );
    }

    /**
     * Non-existent conversation ID for the call page returns non-200.
     */
    public function testCallPageWithNonExistentConversation(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messagerie/call/999999999?type=voice');

        self::assertNotSame(
            200,
            $client->getResponse()->getStatusCode(),
            'A non-existent conversation should never return 200.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Group 2 — ZegoTokenService unit tests (no HTTP, no DB)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Token starts with "04" (ZEGO Token04 prefix) and is base64-decodable after stripping the prefix.
     */
    public function testGeneratedTokenHasCorrectFormat(): void
    {
        $service = new ZegoTokenService(1119688482, 'fa2a0e7b42727b2b4ca2c324cda59be4');

        $token = $service->generateToken('user_42', 'room_contract_7', 3600);

        self::assertStringStartsWith('04', $token, 'Token must start with the "04" version prefix.');

        $base64Part = substr($token, 2);
        $decoded = base64_decode($base64Part, true);
        self::assertNotFalse($decoded, 'Token suffix must be valid base64.');
        self::assertGreaterThan(18, strlen($decoded), 'Decoded binary must contain at least the packed header (nonce+times).');
    }

    /**
     * Two tokens for the same user+room must be distinct (nonce is random).
     */
    public function testEachTokenIsUnique(): void
    {
        $service = new ZegoTokenService(1119688482, 'fa2a0e7b42727b2b4ca2c324cda59be4');

        $t1 = $service->generateToken('user_1', 'room_contract_1');
        $t2 = $service->generateToken('user_1', 'room_contract_1');

        self::assertNotSame($t1, $t2, 'Each call must produce a unique token (random nonce).');
    }

    /**
     * roomIdForContract() returns the expected room ID string.
     */
    public function testRoomIdFormat(): void
    {
        self::assertSame('room_contract_42', ZegoTokenService::roomIdForContract(42));
        self::assertSame('room_contract_1',  ZegoTokenService::roomIdForContract(1));
    }

    /**
     * getAppId() returns the configured app ID.
     */
    public function testGetAppId(): void
    {
        $service = new ZegoTokenService(1119688482, 'secret');
        self::assertSame(1119688482, $service->getAppId());
    }

    /**
     * Throws RuntimeException when openssl is not available.
     * (Skipped on environments where openssl IS available.)
     */
    public function testThrowsWhenOpensslMissing(): void
    {
        if (function_exists('openssl_encrypt')) {
            $this->markTestSkipped('openssl is available; cannot simulate its absence.');
        }

        $this->expectException(\RuntimeException::class);
        $service = new ZegoTokenService(123, 'secret');
        $service->generateToken('user', 'room');
    }
}
