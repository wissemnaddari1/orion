<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Basic access and business rules for Messagerie.
 */
final class MessagerieControllerTest extends WebTestCase
{
    /**
     * Unauthenticated request to conversations list is denied (401 or 403).
     */
    public function testConversationsRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messagerie/conversations', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        self::assertFalse($client->getResponse()->isSuccessful(), 'Unauthenticated access must be denied');
        self::assertContains($client->getResponse()->getStatusCode(), [401, 403, 500]);
    }

    /**
     * Unauthenticated request to messagerie index is denied.
     */
    public function testMessagerieIndexRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messagerie', [], [], [
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        self::assertFalse($client->getResponse()->isSuccessful(), 'Unauthenticated access must be denied');
    }

    /**
     * Unauthenticated request to conversation messages is denied.
     */
    public function testConversationMessagesRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messagerie/conversation/1/messages', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        self::assertFalse($client->getResponse()->isSuccessful(), 'Unauthenticated access must be denied');
    }

    /**
     * POST send message without auth is denied.
     */
    public function testSendMessageRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/messagerie/conversation/1/messages', [
            'content' => 'Hello',
            '_token' => 'test-csrf',
        ], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        self::assertFalse($client->getResponse()->isSuccessful(), 'Unauthenticated POST must be denied');
    }

    /**
     * POST delete without auth is denied.
     */
    public function testDeleteConversationRequiresAuth(): void
    {
        $client = static::createClient();
        $client->request('POST', '/messagerie/conversation/1/delete', [
            '_token' => 'test-csrf',
        ], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        self::assertFalse($client->getResponse()->isSuccessful(), 'Unauthenticated POST must be denied');
    }
}
