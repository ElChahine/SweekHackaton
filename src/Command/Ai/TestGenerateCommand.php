<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

class TestGenerateCommand extends Command
{
    public function __construct(private ClaudeClient $claudeClient) 
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:unit_test:create')->setDescription('Generate tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Logique à venir
        return Command::SUCCESS;
    }
}