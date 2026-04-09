<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
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
            ->setDescription('Génère automatiquement un test unitaire et un test fonctionnel pour un fichier donné')
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

        $io->title('Génération des tests pour : ' . $filePath);

        try {
            /** @var ProgressBar $progressBar */
            $progressBar = $io->createProgressBar(6);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - <info>%message%</info>');

            $progressBar->setMessage('Lecture du fichier...');
            $progressBar->start();

            $fileContent = file_get_contents($filePath);

            $progressBar->setMessage('Analyse par l\'IA (Test Unitaire)...');
            $progressBar->advance();

            $systemPromptUnit = "Tu es un Ingénieur QA d'élite et un expert absolu de l'écosystème Symfony et PHPUnit. Ta mission est de produire du code de test d'une qualité chirurgicale, respectant les normes les plus strictes de l'ingénierie logicielle. Tu es reconnu pour ta concision et ton respect absolu des consignes.";

            $userPromptUnit = "Analyse le code PHP suivant et génère sa classe de test unitaire correspondante.\n\n" .
                "CONSIGNES TECHNIQUES ET ARCHITECTURALES :\n" .
                "1. Isolation totale : C'est un test UNITAIRE. Tu dois impérativement utiliser le framework de Mock natif de PHPUnit (\$this->createMock(), etc.) pour toutes les dépendances injectées.\n" .
                "2. Couverture : Teste les cas de succès (chemin nominal) et les cas d'erreurs (exceptions) évidents.\n" .
                "3. Assertions : Utilise les assertions PHPUnit les plus spécifiques possibles.\n\n" .
                "RÈGLES DE FORMATAGE STRICTES (CRITIQUES) :\n" .
                "- INTERDICTION ABSOLUE d'écrire le moindre commentaire dans le code (ni //, ni /* */, ni docblocks d'explication). Le code doit être auto-documenté par le nommage de ses méthodes.\n" .
                "- Réponds UNIQUEMENT avec le code PHP brut.\n" .
                "- N'inclus AUCUN texte d'introduction ou de conclusion.\n" .
                "- N'utilise PAS de balises Markdown ```php ou ``` autour de ta réponse. Commence directement par <?php.\n\n" .
                "Voici le code source à tester :\n" .
                $fileContent;

            $unitTestCode = $this->claudeClient->call($systemPromptUnit, $userPromptUnit);

            $progressBar->setMessage('Nettoyage et sauvegarde (Test Unitaire)...');
            $progressBar->advance();

            $unitTestCode = $this->cleanCode($unitTestCode);
            $unitTestPath = str_replace(['src/', '.php'], ['tests/Unit/', 'Test.php'], $filePath);
            $this->saveFile($unitTestPath, $unitTestCode);

            $progressBar->setMessage('Analyse par l\'IA (Test Fonctionnel)...');
            $progressBar->advance();

            $systemPromptFunc = "Tu es un Ingénieur QA d'élite et un expert absolu de l'écosystème Symfony et PHPUnit. Ta mission est de produire du code de test d'une qualité chirurgicale, respectant les normes les plus strictes de l'ingénierie logicielle. Tu es reconnu pour ta concision et ton respect absolu des consignes.";

            $userPromptFunc = "Analyse le code PHP suivant (qui fait partie d'une application CLI autonome utilisant symfony/console) et génère sa classe de test fonctionnel correspondante.\n\n" .
                "CONSIGNES TECHNIQUES ET ARCHITECTURALES :\n" .
                "1. Intégration : C'est un test FONCTIONNEL. Tu dois hériter de `PHPUnit\\Framework\\TestCase` (et SURTOUT PAS de KernelTestCase).\n" .
                "2. Outil Symfony : Si le code est une Commande, utilise `Symfony\\Component\\Console\\Tester\\CommandTester` pour simuler son exécution. Instancie manuellement une `Application` (Symfony\\Component\\Console\\Application), ajoutes-y la commande, et teste-la.\n" .
                "3. Réalisme : Ne mocke AUCUNE dépendance interne. L'objectif est de tester le câblage réel de l'application.\n\n" .
                "RÈGLES DE FORMATAGE STRICTES (CRITIQUES) :\n" .
                "- NOMMAGE : La classe de test DOIT IMPÉRATIVEMENT prendre le nom de la classe d'origine en y ajoutant le suffixe `FunctionalTest` (exemple : pour `VersionChecker`, la classe doit s'appeler `VersionCheckerFunctionalTest`).\n" .
                "- INCLUS OBLIGATOIREMENT toutes les déclarations `use` nécessaires en haut du fichier.\n" .
                "- INTERDICTION ABSOLUE d'écrire le moindre commentaire dans le code (ni //, ni /* */, ni docblocks d'explication). Le code doit être auto-documenté par le nommage de ses méthodes.\n" .
                "- Réponds UNIQUEMENT avec le code PHP brut.\n" .
                "- N'inclus AUCUN texte d'introduction ou de conclusion.\n" .
                "- N'utilise PAS de balises Markdown ```php ou ``` autour de ta réponse. Commence directement par <?php.\n\n" .
                "Voici le code source à tester :\n" .
                $fileContent;

            $funcTestCode = $this->claudeClient->call($systemPromptFunc, $userPromptFunc);

            $progressBar->setMessage('Nettoyage et sauvegarde (Test Fonctionnel)...');
            $progressBar->advance();

            $funcTestCode = $this->cleanCode($funcTestCode);
            $funcTestPath = str_replace(['src/', '.php'], ['tests/Functional/', 'FunctionalTest.php'], $filePath);
            $this->saveFile($funcTestPath, $funcTestCode);

            $progressBar->setMessage('Terminé !');
            $progressBar->advance();

            $progressBar->finish();
            $io->newLine(2);

            $io->success([
                'Tests générés avec succès !',
                'Unitaire : ' . $unitTestPath,
                'Fonctionnel : ' . $funcTestPath
            ]);

        } catch (\Exception $e) {
            $io->error('Erreur : ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function cleanCode(string $code): string
    {
        $code = str_replace(['```php', '```'], '', $code);

        $startPos = strpos($code, '<?php');
        if ($startPos !== false) {
            $code = substr($code, $startPos);
        }

        return trim($code);
    }

    private function saveFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $content);
    }
}