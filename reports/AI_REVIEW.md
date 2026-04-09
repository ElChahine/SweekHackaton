# 🛡️ Analyse de Sécurité

**Point critique : Suppression du guard sur l'erreur Git**

Le check `!$process->isSuccessful()` a été **silencieusement supprimé**. Si la commande `git diff` échoue (branche inexistante, repo corrompu, pas de git installé), le code continue avec un `$diff` vide ou partiellement rempli, masquant totalement l'origine du problème. C'est une régression fonctionnelle intentionnelle ou non, mais inacceptable en production.

**Point secondaire : Permissions `mkdir` trop permissives**

```php
mkdir($folder, 0777, true); // ← 0777 en prod, sérieusement ?
```
`0755` est le strict minimum acceptable. `0777` donne les droits d'écriture à tous les utilisateurs système. Sur un serveur partagé ou mal configuré, c'est une surface d'attaque réelle.

**Point tertiaire : Chemin de sortie non configurable et non sanitisé**

`'reports/AI_REVIEW.md'` est un chemin relatif qui dépend du `cwd` au moment de l'exécution. Selon comment la commande est invoquée (cron, CI, Docker), ce fichier peut atterrir n'importe où. Utiliser le `kernel.project_dir` injecté est la pratique standard Symfony.

---

# 🚀 Performance & Optimisation

Pas de problème N+1 détecté sur ce diff (pas de boucle, pas d'accès BDD).

**Cependant, un point d'architecture à signaler :**

La `ProgressBar` est démarrée sans aucune valeur de steps (`$progressBar->start()` sans argument). Elle tourne donc en mode "spinner" indéfini pendant toute la durée de l'appel à `$this->analyzer->analyze()`. C'est cosmétique mais trompeur : l'utilisateur ne sait pas si le process avance ou est bloqué. Si le service `analyzer` supporte une forme de streaming ou de callback, il faudrait envisager de passer un step count ou a minima documenter cette limitation.

---

# 🧹 Clean Code & Standards

**1. Commentaires en majuscules ALL-CAPS dans le code de production**
```php
// AJOUT DE L'OPTION D'EXPORT
// MISE EN PLACE DE LA BARRE DE PROGRESSION
// LOGIQUE D'EXPORTATION
```
Ces annotations de type "TODO de dev" n'ont aucune place dans du code mergé. Ce sont des artefacts de développement, pas de la documentation. Un commentaire utile explique le *pourquoi*, pas le *quoi*.

**2. Suppression de l'argument `target` non documentée**
```php
- ->addArgument('target', InputArgument::OPTIONAL, 'La branche cible à comparer')
```
Retrait d'une fonctionnalité sans la moindre mention dans le message de commit ou les commentaires. C'est un **breaking change silencieux** pour quiconque scripte autour de cette commande.

**3. Variable `$folder` inutile et incohérente**
```php
$folder = 'reports';
// ...
$filename = 'reports/AI_REVIEW.md'; // ← $folder n'est même pas réutilisé ici
```
La variable est déclarée puis ignorée. C'est du code mort immédiatement.

**4. Typage de retour manquant sur `configure()`**
La méthode `configure()` devrait déclarer `: void` explicitement. PSR-12 + Symfony best practices.

**5. Écrasement silencieux du fichier export**
`file_put_contents` écrase sans avertissement un `AI_REVIEW.md` existant. Aucune vérification, aucun timestamp dans le nom de fichier. En CI avec plusieurs reviews, la dernière gagne en silence.

---

# 📈 Score Qualité : 54/100

**Verdict :** ❌ REJECTED

| Pénalité | Raison | Points |
|---|---|---|
| Sécurité | Suppression du guard `isSuccessful()` — régression de gestion d'erreur | -30 |
| Sécurité | `mkdir 0777` | inclus ci-dessus |
| Bug potentiel | Chemin relatif non contrôlé + écrasement silencieux | -15 |
| PSR / Standards | 3 occurrences : commentaires ALL-CAPS, `$folder` mort, typage `void` manquant | -15 |

*La suppression du guard Git à elle seule justifie le rejet. Le diff introduit plus de régressions qu'il n'apporte de valeur.*

---

# 🛠️ Patch Proposé

**File:** `src/Command/Ai/CodeReviewCommand.php`

```php
protected function configure(): void
{
    $this
        ->setName('ai:review')
        ->setDescription('Analyse le code via l\'IA avec export et progression')
        ->addArgument('base', InputArgument::OPTIONAL, 'La branche ou le commit de base', 'HEAD')
        ->addOption('context', 'c', InputOption::VALUE_OPTIONAL, 'Contexte spécifique du projet', 'Standard')
        ->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter la review dans un fichier AI_REVIEW.md');
}

protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);

    $process = new Process(['git', 'diff', $input->getArgument('base')]);
    $process->run();

    // Guard obligatoire : un échec Git ne doit jamais passer en silence
    if (!$process->isSuccessful()) {
        $io->error('Erreur Git : ' . $process->getErrorOutput());
        return Command::FAILURE;
    }

    $diff = $process->getOutput();

    if (empty($diff)) {
        $io->warning('Aucune modification détectée. Review annulée.');
        return Command::SUCCESS;
    }

    $io->title("Audit de code par l'IA de sweeek");

    try {
        $io->note('Analyse du diff par Claude en cours...');
        $progressBar = $io->createProgressBar();
        $progressBar->start();

        $report = $this->analyzer->analyze($diff, $input->getOption('context'));

        $progressBar->finish();
        $io->newLine(2);

        $io->section('Rapport de Review :');
        $io->writeln($report);

        if ($input->getOption('export')) {
            $this->exportReport($report, $io);
        }

        $io->success('Review terminée avec succès.');

    } catch (\Exception $e) {
        $io->error('Erreur lors de l\'analyse : ' . $e->getMessage());
        return Command::FAILURE;
    }

    return Command::SUCCESS;
}

private function exportReport(string $report, SymfonyStyle $io): void
{
    // Chemin ancré sur la racine du projet, jamais relatif au cwd
    $directory = $this->projectDir . '/reports';
    $filename  = $directory . '/AI_REVIEW_' . date('Y-m-d_His') . '.md';

    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        $io->error("Impossible de créer le dossier de rapports : $directory");
        return;
    }

    if (file_put_contents($filename, $report) === false) {
        $io->error("Échec de l'écriture du rapport dans : $filename");
        return;
    }

    $io->success("Rapport exporté avec succès : $filename");
}
```

> **Note :** `$this->projectDir` doit être injecté via le constructeur avec le paramètre `%kernel.project_dir%`. Le nommage du fichier avec timestamp (`Y-m-d_His`) évite les écrasements silencieux en environnement CI/CD.