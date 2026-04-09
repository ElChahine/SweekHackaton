<?php

declare(strict_types=1);

namespace Walibuy\Sweeecli\Command\Ai;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->setName('ai:document')
             ->setDescription('Generate documentation')
             ->addArgument('file', InputArgument::REQUIRED, 'Le chemin du fichier à documenter')
             ->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Format de sortie (markdown ou json)', 'markdown');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');
        $format = strtolower($input->getOption('format'));
        
        $output->writeln("<info> Analyse de $filePath au format $format ...</info>");

        if (!file_exists($filePath)) {
            $output->writeln("<error> Le fichier $filePath est introuvable !</error>");
            return Command::FAILURE;
        }

        $fileContent = file_get_contents($filePath);
        $userPrompt = "Voici le code :\n\n" . $fileContent;

        $output->writeln('<info> Préparation de l\'arborescence...</info>');
        
        $cheminSansSrc = preg_replace('#^src/#', '', $filePath);
        $dossierRelatif = dirname($cheminSansSrc);
        $dossierCible = getcwd() . '/docs/' . $dossierRelatif;
        $nomFichierBase = basename($cheminSansSrc, '.php');
        $extension = ($format === 'json') ? '.json' : '.md';

        if (!is_dir($dossierCible)) {
            mkdir($dossierCible, 0777, true);
        }

        try {

            $output->writeln('<info> Envoi à Claude pour la doc FONCTIONNELLE...</info>');
            
            $systemPromptFonct = "Tu es un expert développeur chez sweeek. CONTEXTE->
L'application est un écosystème e-commerce. La performance (logistique, temps de réponse) et la sécurité sont tes priorités absolues. 
Génère UNIQUEMENT la documentation FONCTIONNELLE (à quoi sert le code, ce qu'il fait pour l'utilisateur).
            RÈGLE ABSOLUE : Tu dois répondre UNIQUEMENT au format $format pur. Ne mets aucun texte d'introduction ou de conclusion.";
            
            $reponseFonct = $this->claudeClient->call($systemPromptFonct, $userPrompt);
            $reponseFonct = trim(str_replace(['```json', '```markdown', '```php', '```'], '', $reponseFonct));

            $cheminFonct = $dossierCible . '/' . $nomFichierBase . '_fonctionnel' . $extension;
            file_put_contents($cheminFonct, $reponseFonct);
            $output->writeln("<info> Succès ! Fichier fonctionnel : $cheminFonct</info>");


            // 2. GÉNÉRATION DE LA DOC TECHNIQUE
            $output->writeln('<info> Envoi à Claude pour la doc TECHNIQUE...</info>');
            
            $systemPromptTech = "Tu es un expert développeur chez sweeek. Génère UNIQUEMENT la documentation TECHNIQUE (comment le code fonctionne sous le capot, pour un développeur).
            RÈGLE ABSOLUE : Tu dois répondre UNIQUEMENT au format $format pur. Ne mets aucun texte d'introduction ou de conclusion.";
            
            $reponseTech = $this->claudeClient->call($systemPromptTech, $userPrompt);
            $reponseTech = trim(str_replace(['```json', '```markdown', '```php', '```'], '', $reponseTech));

            $cheminTech = $dossierCible . '/' . $nomFichierBase . '_technique' . $extension;
            file_put_contents($cheminTech, $reponseTech);
            $output->writeln("<info> Succès ! Fichier technique : $cheminTech</info>");

        } catch (\Exception $e) {
            $output->writeln('<error> Erreur : ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info> Parfait ! Les deux documentations ont été générées.</info>');
        return Command::SUCCESS;
    }
}