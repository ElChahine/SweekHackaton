<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

class DocumentationGenerateCommand extends Command
{
    public function __construct(private ClaudeClient $claudeClient) 
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:document')->setDescription('Generate documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return Command::SUCCESS;
    }
}