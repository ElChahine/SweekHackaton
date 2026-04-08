<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Anthropic\PlatformFactory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClaudeTestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:claude-test')
            ->setDescription('Send a prompt to Claude using an API key from .env')
            ->addArgument('prompt', InputArgument::REQUIRED, 'Prompt to send to Claude')
            ->addOption('model', 'm', InputOption::VALUE_OPTIONAL, 'Claude model name', Claude::SONNET_4_0)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? $_ENV['CLAUDE_API_KEY'] ?? '';
        if ('' === trim($apiKey)) {
            $io->error('Missing API key. Add ANTHROPIC_API_KEY="..." (or CLAUDE_API_KEY="...") in .env.');

            return self::FAILURE;
        }

        $model = (string) $input->getOption('model');
        $prompt = (string) $input->getArgument('prompt');

        try {
            $platform = PlatformFactory::create($apiKey);
            $answer = $platform
                ->invoke($model, new MessageBag(Message::ofUser($prompt)))
                ->asText();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Claude request failed: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $io->success(sprintf('Response received from model %s', $model));
        $io->writeln($answer);

        return self::SUCCESS;
    }
}
