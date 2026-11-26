<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/recommendations', name: 'recommendations.')]
final class ConversationController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock = new NativeClock(),
        #[Autowire('%env(int:CONVERSATION_TTL_MINUTES)%')]
        private readonly int $conversationTtlMinutes = 30,
    ) {
    }

    #[Route('/conversations', name: 'conversations.list', methods: ['GET'])]
    public function index(#[CurrentUser] JwtUser $user): JsonResponse
    {
        $now = $this->clock->now();
        $cutoff = $now->modify(\sprintf('-%d minutes', $this->conversationTtlMinutes));

        /** @var list<Conversation> $items */
        $items = $this->conversations->createQueryBuilder('c')
            ->where('c.userId = :user')
            ->andWhere('c.expiresAt > :now')
            ->andWhere('c.lastActivityAt > :cutoff')
            ->setParameter('user', $user->getId())
            ->setParameter('now', $now)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('c.lastActivityAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();

        return $this->json([
            'data' => array_map(static fn (Conversation $c): array => [
                'id' => (string) $c->getId(),
                'store_id' => $c->getStoreId(),
                'created_at' => $c->getCreatedAt()->format(\DATE_ATOM),
                'expires_at' => $c->getExpiresAt()->format(\DATE_ATOM),
            ], $items),
        ]);
    }

    #[Route('/conversations/{id}', name: 'conversations.show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] JwtUser $user): JsonResponse
    {
        $conversation = $this->owned($id, $user);

        return $this->json([
            'id' => $id,
            'store_id' => $conversation->getStoreId(),
            'messages' => array_map(
                static fn (Message $m): array => [
                    'id' => (string) $m->getId(),
                    'role' => $m->getRole()->value,
                    'content' => $m->getContent(),
                    'products' => $m->getSuggestedProducts(),
                    'filters' => $m->getExtractedFilters(),
                    'created_at' => $m->getCreatedAt()->format(\DATE_ATOM),
                ],
                $conversation->getMessages()->toArray(),
            ),
        ]);
    }

    #[Route('/conversations/{id}', name: 'conversations.delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] JwtUser $user): JsonResponse
    {
        $this->entityManager->remove($this->owned($id, $user));
        $this->entityManager->flush();

        return new JsonResponse(null, \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT);
    }

    private function owned(string $id, JwtUser $user): Conversation
    {
        $conversation = Uuid::isValid($id) ? $this->conversations->find(Uuid::fromString($id)) : null;
        $now = $this->clock->now();
        if (
            !$conversation instanceof Conversation
            || $conversation->getUserId() !== $user->getId()
            || $conversation->isExpired($this->conversationTtlMinutes, $now)
        ) {
            throw $this->createNotFoundException('Диалог не найден.');
        }

        return $conversation;
    }
}
