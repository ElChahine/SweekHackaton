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
    protected static $defaultName = 'ai:tests:create';

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
             ->setDescription('Génère une suite de tests complète (Analyse -> Fixtures -> Code)')
             ->addArgument('file', InputArgument::REQUIRED, 'Le chemin du fichier PHP à tester')
             ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Le type de test : unit ou functional', 'unit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceFile = (string) $input->getArgument('file');
        $type = (string) $input->getOption('type');

        if (!file_exists($sourceFile)) {
            $io->error("Le fichier source n'existe pas : $sourceFile");
            return Command::FAILURE;
        }

        $io->title("🚀 Orchestration Multi-Agents : Génération de tests ($type)");

        try {
            $sourceCode = file_get_contents($sourceFile);

            // Étape 1 : Analyse de la stratégie
            $io->section("Agent 1 : Analyse de la stratégie...");
            $strategy = $this->analyzer->analyze($sourceCode);
            $io->writeln("<info>Stratégie définie avec succès.</info>");

            // Étape 2 : Génération des Fixtures
            $io->section("Agent 2 : Préparation des données (Fixtures)...");
            // CORRECTION : On passe bien les deux arguments attendus par FixtureGenerator
            $fixtures = $this->fixtureGenerator->generate($sourceCode, $strategy);
            $io->writeln("<info>Jeux de données préparés.</info>");

            // Étape 3 : Rédaction
            $io->section("Agent 3 : Rédaction du code de test...");
            $finalResponse = $this->generator->generate($sourceCode, $strategy, $fixtures, $type);

            // --- NOUVELLE LOGIQUE DE SÉPARATION ---
            
            // 1. On cherche où commence le code PHP
            $phpStartPos = strpos($finalResponse, '<?php');
            
            if ($phpStartPos === false) {
                throw new \Exception("L'IA n'a pas généré de code PHP valide (balise <?php manquante).");
            }

            // 2. On sépare le texte (Analyse) du code PHP
            $textAnalysis = trim(substr($finalResponse, 0, $phpStartPos));
            $phpCode = trim(substr($finalResponse, $phpStartPos));

            // 3. Nettoyage final du code (on enlève les éventuelles balises ``` à la fin)
            $phpCode = preg_replace('/```$/', '', $phpCode);

            // --- SAUVEGARDE DES DEUX LIVRABLES ---
            $fileName = basename($sourceFile, '.php');
            $outputDir = 'tests/Generated/' . ($type === 'unit' ? 'Unit' : 'Functional');
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }

            // Sauvegarde du code PHP (Le Test)
            $testPath = $outputDir . '/' . $fileName . 'Test.php';
            file_put_contents($testPath, $phpCode);

            // Sauvegarde de l'analyse (Le Rapport)
            $reportPath = $outputDir . '/' . $fileName . 'Test_Report.md';
            file_put_contents($reportPath, $textAnalysis);

            $io->newLine();
            $io->success([
                "Tests et Analyse générés avec succès !",
                "Code de test : $testPath",
                "Rapport d'analyse : $reportPath"
            ]);

        } catch (\Exception $e) {
            $io->error("Erreur lors de la génération : " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}