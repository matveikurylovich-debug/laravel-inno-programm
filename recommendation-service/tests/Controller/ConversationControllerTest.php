<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConversationControllerTest extends WebTestCase
{
    public function test_history_is_not_public(): void
    {
        $client = static::createClient();
        $client->request('GET', '/recommendations/conversations');
        self::assertResponseStatusCodeSame(401);
    }

    public function test_forged_token_cannot_delete_a_conversation(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/recommendations/conversations/018f0534-6b71-7d9c-841c-a2a33c9ef15e', server: ['HTTP_AUTHORIZATION' => 'Bearer forged']);
        self::assertResponseStatusCodeSame(401);
    }
}
