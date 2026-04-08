<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

class TestGenerateCommand extends Command
{
    public function __construct(
        private ClaudeClient $claudeClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai:unit_test:create')
            ->setDescription('Laboratoire de test pour Claude AI')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Test Lab Claude :');

        try {
            $io->text('Envoi d\'un message de test à Claude...');
            
            $response = $this->claudeClient->call(
                "Tu es un assistant de test technique.", 
                "Réponds exactement le mot 'OK' si tu reçois ce message."
            );

            $io->success('Claude a répondu : ' . $response);
        } catch (\Exception $e) {
            $io->error('Échec du test : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}