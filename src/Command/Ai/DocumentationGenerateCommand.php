<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Walibuy\Sweeecli\Core\Ai\ClaudeClient;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;



class DocumentationGenerateCommand extends Command
{
    public function __construct(private ClaudeClient $claudeClient) 
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:document')
             ->setDescription('Generate documentation')
             ->addArgument('file', InputArgument::REQUIRED, 'Le chemin du fichier à documenter')
             // ajoute l'option --format, optionnelle, qui vaut 'markdown' par défaut
             ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Format de sortie (markdown ou json)', 'markdown');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        // récupère le format demandé (et on le met en minuscules par sécurité)
        $format = strtolower($input->getOption('format'));
        
        $output->writeln("<info> Analyse de $filePath au format $format ...</info>");

        if (!file_exists($filePath)) {
            $output->writeln("<error> Le fichier $filePath est introuvable !</error>");
            return Command::FAILURE;
        }

        $fileContent = file_get_contents($filePath);

        // adapte les consignes de l'IA en fonction du format choisi
        $systemPrompt = "Tu es un expert développeur. Génère la documentation technique et fonctionnelle du code fourni.
        RÈGLE ABSOLUE : Tu dois répondre UNIQUEMENT au format $format. 
        Ne mets aucun texte d'introduction ou de conclusion, donne uniquement le code $format pur.";

        $userPrompt = "Voici le code :\n\n" . $fileContent;

        try {
            $output->writeln('Envoi à Claude...');
            $reponseIa = $this->claudeClient->call($systemPrompt, $userPrompt);

            // retire les balises markdown que l'IA pourrait rajouter
            $reponseIa = str_replace(['```json', '```markdown', '```php', '```'], '', $reponseIa);
            // enlève les espaces ou sauts de ligne en trop au début et à la fin
            $reponseIa = trim($reponseIa);



            $output->writeln('<info> Création de l\'arborescence et du fichier...</info>');

            // enlève le "src/"
            $cheminSansSrc = preg_replace('#^src/#', '', $filePath);

            // récupère juste le nom du dossier
            $dossierRelatif = dirname($cheminSansSrc);

            // crée le chemin du dossier cible dans "docs"
            $dossierCible = getcwd() . '/docs/' . $dossierRelatif;

            // crée les dossiers s'ils n'existent pas
            if (!is_dir($dossierCible)) {
                mkdir($dossierCible, 0777, true);
            }

            // prépare le nom du fichier
            $nomFichierBase = basename($cheminSansSrc, '.php');
            $extension = ($format === 'json') ? '.json' : '.md';
            
            // assemble le chemin final
            $cheminFinal = $dossierCible . '/' . $nomFichierBase . $extension;

            // sauvegarde la réponse de Claude dans le fichier
            file_put_contents($cheminFinal, $reponseIa);

            $output->writeln("<info> Succès ! La documentation a été sauvegardée ici : $cheminFinal</info>");

        } catch (\Exception $e) {
            $output->writeln('<error> Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}