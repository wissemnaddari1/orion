<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthApiControllerTest extends WebTestCase
{
    /**
     * POST /api/login with empty body returns 400.
     */
    public function testLoginEmptyBodyReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{}');

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertSame('invalid_credentials', $data['error']);
    }

    /**
     * POST /api/login with missing password returns 400.
     */
    public function testLoginMissingPasswordReturns400(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"email":"someone@example.com"}');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * POST /api/login with non-existent user returns 401.
     */
    public function testLoginInvalidCredentialsReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{"email":"nonexistent@example.com","password":"any"}');

        self::assertResponseStatusCodeSame(401);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $data);
        self::assertSame('invalid_credentials', $data['error']);
    }

    /**
     * POST /api/logout returns 200 and clears cookie.
     */
    public function testLogoutReturns200(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/logout');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('message', $data);
    }
}
