<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->setName('ai:test:generate')
            ->setDescription('Génère automatiquement un test PHPUnit pour un fichier donné')
            ->addArgument('filePath', InputArgument::REQUIRED, 'Le chemin du fichier PHP à tester (ex: src/Core/Updater/VersionChecker.php)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('filePath');

        if (!file_exists($filePath)) {
            $io->error(sprintf('Le fichier "%s" n\'existe pas.', $filePath));
            return Command::FAILURE;
        }

        $fileContent = file_get_contents($filePath);
        $io->title('Génération du test pour : ' . $filePath);

        try {
            $systemPrompt = "Tu es un expert en tests unitaires PHPUnit et Symfony.";
            $userPrompt = "Génère uniquement le code d'une classe de test PHPUnit pour le code suivant.\n" .
                "CONSIGNES :\n" .
                "- C'est un TEST UNITAIRE : utilise des Mocks.\n" .
                "- Réponds UNIQUEMENT avec le code PHP.\n" .
                "- INTERDICTION d'écrire du texte ou des balises ``` avant ou après le code.\n\n" .
                $fileContent;

            $io->text('Analyse du code par Claude...');
            $testCode = $this->claudeClient->call($systemPrompt, $userPrompt);

            // --- NETTOYAGE ULTRA-ROBUSTE ---

            // 1. Supprimer les balises Markdown ```php et ```
            $testCode = str_replace(['```php', '```'], '', $testCode);

            // 2. Trouver la position réelle du tag <?php
            $startPos = strpos($testCode, '<?php');
            if ($startPos !== false) {
                // On coupe tout ce qu'il y a AVANT le <?php pour supprimer texte et espaces invisibles
                $testCode = substr($testCode, $startPos);
            }

            // 3. Nettoyage final des espaces en début/fin
            $testCode = trim($testCode);

            // 4. Logique de chemin : src/ -> tests/ et .php -> Test.php
            $testPath = str_replace(['src/', '.php'], ['tests/', 'Test.php'], $filePath);

            $directory = dirname($testPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            // 5. Sauvegarde
            file_put_contents($testPath, $testCode);

            $io->success([
                'Test généré sans erreurs de syntaxe !',
                'Fichier : ' . $testPath
            ]);

        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}