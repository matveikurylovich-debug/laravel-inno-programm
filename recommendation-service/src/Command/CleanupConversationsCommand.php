<?php

declare(strict_types=1);
namespace App\Command;

use App\Entity\Conversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:conversations:cleanup', description: 'Delete expired and inactive conversations, including their messages')]
final class CleanupConversationsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->entityManager->createQueryBuilder()->delete(Conversation::class, 'c')
            ->where('c.expiresAt <= :now OR c.lastActivityAt <= :cutoff')
            ->setParameter('now', new \DateTimeImmutable())->setParameter('cutoff', new \DateTimeImmutable('-30 minutes'))
            ->getQuery()->execute();
        $output->writeln(sprintf('Deleted %d expired conversations.', $count));
        return Command::SUCCESS;
    }
}
