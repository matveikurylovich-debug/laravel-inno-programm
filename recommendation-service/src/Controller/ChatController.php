<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChatRequest;
use App\Dto\ChatResponse;
use App\Entity\Conversation;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use App\Service\ChatQuota;
use App\Service\RecommendationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/recommendations', name: 'recommendations.')]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly RecommendationEngine $recommendationEngine,
        private readonly ConversationRepository $conversations,
        private readonly ChatQuota $chatQuota,
        private readonly ClockInterface $clock = new NativeClock(),
        #[Autowire('%env(int:CONVERSATION_TTL_MINUTES)%')]
        private readonly int $conversationTtlMinutes = 30,
    ) {
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(
        #[MapRequestPayload]
        ChatRequest $dto,
        #[CurrentUser]
        JwtUser $user,
    ): JsonResponse {
        $retryAfter = $this->chatQuota->consume($user->getId());
        if (null !== $retryAfter) {
            throw new TooManyRequestsHttpException($retryAfter, 'Слишком много сообщений. Подождите и попробуйте снова.');
        }

        $conversation = $this->resolveConversation($dto, $user);

        $result = $this->recommendationEngine->processUserMessage(
            $conversation,
            $dto->message,
        );

        $response = new ChatResponse(
            conversation_id: (string) $conversation->getId(),
            reply: $result['reply'],
            products: $result['products'],
            filters: $result['filters'],
        );

        return $this->json($response);
    }

    private function resolveConversation(ChatRequest $dto, JwtUser $user): Conversation
    {
        $now = $this->clock->now();
        if (null === $dto->conversation_id || '' === $dto->conversation_id) {
            return new Conversation($user->getId(), $dto->store_id, $now);
        }

        $existing = $this->conversations->find(Uuid::fromString($dto->conversation_id));
        if (!$existing instanceof Conversation) {
            return new Conversation($user->getId(), $dto->store_id, $now);
        }

        if ($existing->getUserId() !== $user->getId()) {
            throw new NotFoundHttpException('Диалог не найден.');
        }

        if ($existing->isExpired($this->conversationTtlMinutes, $now)) {
            return new Conversation($user->getId(), $dto->store_id, $now);
        }

        return $existing;
    }
}
