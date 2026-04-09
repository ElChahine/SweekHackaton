<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Walibuy\Sweeecli\Core\Ai\TestAnalyzer;
use Walibuy\Sweeecli\Core\Ai\FixtureGenerator;
use Walibuy\Sweeecli\Core\Ai\TestGenerator;

class TestGenerateCommand extends Command
{
    public function __construct(
        private TestAnalyzer $analyzer,
        private FixtureGenerator $fixtureGenerator,
        private TestGenerator $generator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:tests:create')
             ->addArgument('file', InputArgument::REQUIRED, 'Fichier à tester')
             ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'unit ou functional', 'unit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceCode = file_get_contents($input->getArgument('file'));

        $io->title("🚀 Orchestration Multi-Agents : Génération de tests");

        try {
            // Étape 1 : Analyse
            $io->section("Agent 1 : Analyse de la stratégie...");
            $strategy = $this->analyzer->analyze($sourceCode);
            $io->note($strategy);

            // Étape 2 : Fixtures
            $io->section("Agent 2 : Création des fixtures...");
            $fixtures = $this->fixtureGenerator->generate($sourceCode, $strategy);
            
            // Étape 3 : Rédaction
            $io->section("Agent 3 : Rédaction du code de test...");
            $finalCode = $this->generator->generate($sourceCode, $strategy, $fixtures, $input->getOption('type'));

            $io->writeln($finalCode);
            $io->success("Tests générés avec succès via workflow Multi-Agents.");
            
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}