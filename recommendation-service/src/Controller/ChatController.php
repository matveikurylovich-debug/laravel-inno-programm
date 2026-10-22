<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChatRequestDto;
use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use App\Service\RecommendationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/recommendations', name: 'api_recommendations_')]
final class ChatController
{
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(
        #[MapRequestPayload] ChatRequestDto $dto,
        #[CurrentUser] JwtUser $user,
    ): JsonResponse {
        $conversation = $this->resolveConversation($dto, $user);

        if (!$this->entityManager->contains($conversation)) {
            $this->entityManager->persist($conversation);
        }

        $result = $this->recommendationEngine->processUserMessage(
            $conversation,
            $dto->message,
            $dto->store_id,
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
            throw new AccessDeniedHttpException('Диалог принадлежит другому пользователю.');
        }

        return $existing;
    }
}
