<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ConversationController;
use App\Entity\Conversation;
use App\EventListener\ProblemJsonExceptionListener;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ConversationControllerTest extends WebTestCase
{
    public function testHistoryIsNotPublic(): void
    {
        $client = self::createClient();
        $client->request('GET', '/recommendations/conversations');
        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('content-type'));
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(401, $body['status']);
        $this->assertSame('Unauthorized', $body['title']);
        $this->assertNotSame('', $body['detail']);
    }

    public function testForgedTokenCannotDeleteAConversation(): void
    {
        $client = self::createClient();
        $client->request(
            'DELETE',
            '/recommendations/conversations/018f0534-6b71-7d9c-841c-a2a33c9ef15e',
            server: ['HTTP_AUTHORIZATION' => 'Bearer forged'],
        );
        $this->assertResponseStatusCodeSame(401);
        $this->assertStringContainsString('application/problem+json', (string) $client->getResponse()->headers->get('content-type'));
        /** @var array<string, mixed> $forged */
        $forged = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(401, $forged['status']);
        $this->assertSame('Требуется авторизация.', $forged['detail']);
    }

    public function testAnotherUserCannotReadOrDeleteConversation(): void
    {
        $client = self::createClient();
        $foreignConversation = new Conversation(99, 1);

        $conversations = $this->createStub(ConversationRepository::class);
        $conversations->method('find')->willReturn($foreignConversation);

        $container = self::getContainer();
        $controller = new ConversationController(
            $conversations,
            $this->createStub(EntityManagerInterface::class),
        );
        $controller->setContainer($container);
        $container->set(ConversationController::class, $controller);

        $privateKey = (string) file_get_contents(\dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/access-private.pem');
        $token = new AccessTokenSigner($privateKey, 'test-1', 'http://auth-app', 'ecommerce')->issue(42, ['customer']);

        $client->request(
            'GET',
            '/recommendations/conversations/'.$foreignConversation->getId(),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $this->assertResponseStatusCodeSame(404);
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(404, $body['status']);
    }

    public function testConfigurableTtlAndClockExpireInactiveConversation(): void
    {
        self::bootKernel();
        $createdAt = new DateTimeImmutable('2026-11-25T12:00:00+00:00');
        $conversation = new Conversation(7, 1, $createdAt);

        $conversations = $this->createStub(ConversationRepository::class);
        $conversations->method('find')->willReturn($conversation);

        $clock = new MockClock('2026-11-25T12:16:00+00:00');
        $controller = new ConversationController(
            $conversations,
            $this->createStub(EntityManagerInterface::class),
            $clock,
            15,
        );
        $controller->setContainer(self::getContainer());

        $this->expectException(NotFoundHttpException::class);
        $controller->show((string) $conversation->getId(), new JwtUser(7, '', ['ROLE_USER']));
    }

    public function testValidationFailureIsProblemJson(): void
    {
        $client = self::createClient();
        $request = Request::create('/recommendations/chat', 'POST');
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Сообщение обязательно', null, [], null, 'message', null),
        ]);
        $exception = new UnprocessableEntityHttpException(
            'invalid',
            new ValidationFailedException(null, $violations),
        );
        $event = new ExceptionEvent(
            $client->getKernel(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );

        new ProblemJsonExceptionListener()($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
        $this->assertSame(\Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), (string) $response->getContent());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame('Сообщение обязательно', $body['detail']);
        $this->assertIsArray($body['errors']);
        $this->assertSame(['Сообщение обязательно'], $body['errors']['message']);

        $missing = new ExceptionEvent(
            $client->getKernel(),
            Request::create('/recommendations/conversations/missing', 'GET'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException(Conversation::class),
        );
        new ProblemJsonExceptionListener()($missing);
        /** @var array<string, mixed> $notFound */
        $notFound = json_decode((string) $missing->getResponse()?->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(404, $notFound['status']);
        $this->assertSame('Ресурс не найден.', $notFound['detail']);
    }
}
