<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Conversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsCommand(
    name: 'recommendation:cleanup-conversations',
    description: 'Delete expired and inactive conversations, including their messages',
)]
#[AsPeriodicTask('1 minute', schedule: 'default')]
final class CleanupConversationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock = new NativeClock(),
        #[Autowire('%env(int:CONVERSATION_TTL_MINUTES)%')]
        private readonly int $conversationTtlMinutes = 30,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now();
        $cutoff = $now->modify(\sprintf('-%d minutes', $this->conversationTtlMinutes));

        $count = $this->entityManager->createQueryBuilder()
            ->delete(Conversation::class, 'c')
            ->where('c.expiresAt <= :now OR c.lastActivityAt <= :cutoff')
            ->setParameter('now', $now)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        $output->writeln(\sprintf('Deleted %d expired conversations.', $count));

        return Command::SUCCESS;
    }
}
