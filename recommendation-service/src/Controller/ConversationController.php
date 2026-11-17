<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ConversationRepository;
use App\Security\JwtUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Uid\Uuid;

#[Route('/recommendations/conversations', name: 'recommendations_conversations_')]
final class ConversationController extends AbstractController
{
    public function __construct(private readonly ConversationRepository $conversations, private readonly EntityManagerInterface $entityManager) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(#[CurrentUser] JwtUser $user): JsonResponse
    {
        $items = $this->conversations->createQueryBuilder('c')
            ->where('c.userId = :user')->andWhere('c.expiresAt > :now')->andWhere('c.lastActivityAt > :cutoff')
            ->setParameter('user', $user->getId())->setParameter('now', new \DateTimeImmutable())
            ->setParameter('cutoff', new \DateTimeImmutable('-30 minutes'))
            ->orderBy('c.lastActivityAt', 'DESC')->setMaxResults(100)->getQuery()->getResult();
        return $this->json(['data' => array_map(static fn (Conversation $c): array => [
            'id' => (string) $c->getId(), 'store_id' => $c->getStoreId(),
            'created_at' => $c->getCreatedAt()->format(DATE_ATOM), 'expires_at' => $c->getExpiresAt()->format(DATE_ATOM),
        ], $items)]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] JwtUser $user): JsonResponse
    {
        $conversation = $this->owned($id, $user);
        return $this->json(['id' => $id, 'store_id' => $conversation->getStoreId(), 'messages' => array_map(
            static fn (Message $m): array => ['id' => (string) $m->getId(), 'role' => $m->getRole(),
                'content' => $m->getContent(), 'products' => $m->getSuggestedProducts(),
                'filters' => $m->getExtractedFilters(), 'created_at' => $m->getCreatedAt()->format(DATE_ATOM)],
            $conversation->getMessages()->toArray(),
        )]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] JwtUser $user): JsonResponse
    {
        $this->entityManager->remove($this->owned($id, $user));
        $this->entityManager->flush();
        return new JsonResponse(null, 204);
    }

    private function owned(string $id, JwtUser $user): Conversation
    {
        $conversation = Uuid::isValid($id) ? $this->conversations->find(Uuid::fromString($id)) : null;
        if (!$conversation instanceof Conversation || $conversation->getUserId() !== $user->getId() || $conversation->isExpired()) {
            throw $this->createNotFoundException('Диалог не найден.');
        }
        return $conversation;
    }
}
