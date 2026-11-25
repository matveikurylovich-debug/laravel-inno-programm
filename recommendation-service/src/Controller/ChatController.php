<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChatRequestDto;
use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use App\Service\ChatQuota;
use App\Service\RecommendationEngine;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/recommendations', name: 'api_recommendations_')]
final class ChatController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatQuota $chatQuota,
    ) {
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    #[OA\Post(
        path: '/recommendations/chat',
        summary: 'Чат рекомендаций',
        security: [['bearerAuth' => []]],
        tags: ['Recommendations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['message', 'store_id'],
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'store_id', type: 'integer'),
                    new OA\Property(property: 'conversation_id', type: 'string', nullable: true, format: 'uuid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ответ ассистента и товары'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 404, description: 'Диалог не найден'),
        ]
    )]
    public function chat(
        #[MapRequestPayload] ChatRequestDto $dto,
        #[CurrentUser] JwtUser $user,
    ): JsonResponse {
        $retryAfter = $this->chatQuota->consume($user->getId());
        if ($retryAfter !== null) {
            return new JsonResponse([
                'error' => 'Слишком много сообщений. Подождите и попробуйте снова.',
            ], 429, ['Retry-After' => (string) $retryAfter]);
        }

        $conversation = $this->resolveConversation($dto, $user);

        if (!$this->entityManager->contains($conversation)) {
            $this->entityManager->persist($conversation);
        }

        $result = $this->recommendationEngine->processUserMessage(
            $conversation,
            $dto->message,
            $conversation->getStoreId(),
        );

        return new JsonResponse([
            'conversation_id' => (string) $conversation->getId(),
            'reply' => $result['reply'],
            'products' => $result['products'],
            'filters' => $result['extracted_filters'],
        ]);
    }

    private function resolveConversation(ChatRequestDto $dto, JwtUser $user): Conversation
    {
        if ($dto->conversation_id === null || $dto->conversation_id === '') {
            return new Conversation($user->getId(), $dto->store_id);
        }

        $existing = $this->conversations->find(Uuid::fromString($dto->conversation_id));
        if (!$existing instanceof Conversation || $existing->isExpired()) {
            return new Conversation($user->getId(), $dto->store_id);
        }

        if ($existing->getUserId() !== $user->getId()) {
            throw new NotFoundHttpException('Диалог не найден.');
        }

        return $existing;
    }
}
