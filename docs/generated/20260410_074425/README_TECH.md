# 🛠️ Documentation Technique

## Vue d'ensemble

Ce dossier regroupe trois commandes CLI dédiées à l'**intelligence artificielle** au sein de l'outil interne `sweeecli` (namespace `Walibuy\Sweeecli`). Ces commandes constituent la **couche de présentation CLI** d'un système d'analyse de code par IA (Claude), orchestrant des agents spécialisés pour trois cas d'usage distincts :

| Commande | Alias CLI | Rôle |
|---|---|---|
| `CodeReviewCommand` | `ai:review` | Analyse un diff Git ou un fichier source via IA |
| `DocumentationGenerateCommand` | `ai:doc:generate` | Génère une double documentation (Tech + Fonctionnelle) |
| `TestGenerateCommand` | `ai:tests:create` | Orchestre un pipeline multi-agents pour générer des tests |

### Choix architecturaux notables

- **Pattern Command (Symfony Console)** : Chaque classe étend `Symfony\Component\Console\Command\Command`, conformément au contrat Symfony Console. La logique métier est **déléguée à des Analyzers** (`ReviewAnalyzer`, `DocAnalyzer`, `TestAnalyzer`), respectant le principe de responsabilité unique (SRP).
- **Injection de dépendances via constructeur** : Toutes les dépendances sont injectées par constructeur (immutabilité garantie via `private readonly` implicite avec les propriétés promues PHP 8.1+).
- **Séparation Command / Core** : La couche `Command/Ai/` ne contient **aucune logique métier** — elle gère uniquement l'I/O console et délègue au namespace `Core/Ai/`.

---

# 🗺️ Logique d'Arborescence

```
src/
└── Command/
    └── Ai/                          ← Domaine fonctionnel : IA
        ├── CodeReviewCommand.php
        ├── DocumentationGenerateCommand.php
        └── TestGenerateCommand.php
Core/
└── Ai/                              ← Logique métier isolée
    ├── ReviewAnalyzer.php
    ├── DocAnalyzer.php
    ├── TestAnalyzer.php
    ├── FixtureGenerator.php
    └── TestGenerator.php
```

### Justification du placement

| Niveau | Principe appliqué |
|---|---|
| `Command/Ai/` | **Domain-Driven** : regroupement par domaine fonctionnel (`Ai`), non par type technique. Toutes les commandes IA sont co-localisées, facilitant la découvrabilité. |
| Séparation `Command/` vs `Core/` | **Layered Architecture** : la couche `Command` est la **surface d'entrée** (input/output), `Core` est le **cœur de traitement**. Cette symétrie permet de brancher d'autres surfaces (API HTTP, Worker) sur le même `Core`. |
| Namespace `Walibuy\Sweeecli` | Isolation du projet CLI dans l'écosystème Walibuy/sweeek, évitant les collisions de namespace avec les autres composants. |

---

# 🔄 Interactions (Mermaid)

```mermaid
flowchart TD
    subgraph CLI["Couche CLI (Command/Ai/)"]
        CRC["CodeReviewCommand\n(ai:review)"]
        DGC["DocumentationGenerateCommand\n(ai:doc:generate)"]
        TGC["TestGenerateCommand\n(ai:tests:create)"]
    end

    subgraph CORE["Couche Core (Core/Ai/)"]
        RA["ReviewAnalyzer\n.analyze(content, context)"]
        DA["DocAnalyzer\n.analyze(dir, type, ctx)"]
        TA["TestAnalyzer\n.analyze(sourceCode)"]
        FG["FixtureGenerator\n.generate(source, strategy)"]
        TG["TestGenerator\n.generate(source, strategy, fixtures, type)"]
    end

    subgraph INPUTS["Sources de données"]
        GIT["Git Process\n(git diff)"]
        FS["Filesystem\n(file_get_contents)"]
        DIR["Directory Scanner"]
    end

    subgraph OUTPUTS["Sorties"]
        CONSOLE["Console Output\n(SymfonyStyle)"]
        RPT["reports/AI_REVIEW.md"]
        DOCS["docs/generated/Ymd_His/\n├── README_TECH.md\n└── README_FUNC.md"]
        TESTS["stdout (test code)"]
    end

    GIT -->|diff content| CRC
    FS -->|file content| CRC
    CRC -->|"analyze(content, ctx)"| RA
    RA -->|report string| CRC
    CRC -->|display| CONSOLE
    CRC -->|"--export flag"| RPT

    DIR -->|directory path| DGC
    DGC -->|"analyze(dir, 'technical', ctx)"| DA
    DGC -->|"analyze(dir, 'functional', ctx)"| DA
    DA -->|doc string| DGC
    DGC -->|write| DOCS

    FS -->|source code| TGC
    TGC -->|"analyze(source)"| TA
    TA -->|strategy| TGC
    TGC -->|"generate(source, strategy)"| FG
    FG -->|fixtures| TGC
    TGC -->|"generate(source, strategy, fixtures, type)"| TG
    TG -->|test code| TGC
    TGC -->|display| TESTS

    style CLI fill:#1a3a5c,stroke:#4a9eff,color:#fff
    style CORE fill:#1a4a2e,stroke:#4aff7f,color:#fff
    style INPUTS fill:#3a2a1a,stroke:#ffaa4a,color:#fff
    style OUTPUTS fill:#3a1a3a,stroke:#ff4aff,color:#fff
```

### Pipeline `TestGenerateCommand` — Détail du workflow multi-agents

```mermaid
sequenceDiagram
    actor Dev as Développeur
    participant CLI as TestGenerateCommand
    participant A1 as TestAnalyzer (Agent 1)
    participant A2 as FixtureGenerator (Agent 2)
    participant A3 as TestGenerator (Agent 3)

    Dev->>CLI: ai:tests:create <file> --type=unit
    CLI->>CLI: file_get_contents(file)
    CLI->>A1: analyze(sourceCode)
    A1-->>CLI: strategy (string)
    CLI->>A2: generate(sourceCode, strategy)
    A2-->>CLI: fixtures (data)
    CLI->>A3: generate(sourceCode, strategy, fixtures, "unit")
    A3-->>CLI: finalCode (string)
    CLI-->>Dev: stdout + success message
```

---

# ⚠️ Points de Vigilance Techniques

### 🔴 Critique — Sécurité

**1. Injection de commande via `git diff` (CodeReviewCommand)**
```php
// ⚠️ RISQUE : $base et $target proviennent directement de l'input utilisateur
$gitArgs = ['git', 'diff', $base];
if ($target) {
    $gitArgs[] = $target;
}
$process = new Process($gitArgs);
```
> **Mitigation actuelle** : L'utilisation de `Process` avec un tableau d'arguments (et non une chaîne) prévient l'injection shell. ✅ Correct.
> **Risque résiduel** : Aucune validation du format des arguments (`$base`, `$target`). Un attaquant pourrait passer `--output=/etc/passwd` comme argument Git. **Ajouter une validation regex** sur les noms de branches/commits.

**2. Permissions de dossier trop permissives**
```php
mkdir($folder, 0777, true); // ⚠️ 0777 en production
```
> Présent dans **les 3 commandes**. En environnement serveur partagé, 0777 expose les fichiers générés à tous les processus. **Préférer 0750 ou 0755**.

---

### 🟠 Important — Robustesse

**3. Absence de validation du fichier source dans `TestGenerateCommand`**
```php
$sourceCode = file_get_contents($input->getArgument('file'));
// ⚠️ Pas de vérification is_file(), is_readable(), ni de gestion du false
```
> Contrairement à `CodeReviewCommand` qui effectue des vérifications complètes (`is_file`, `is_readable`, vérification du retour `false`), `TestGenerateCommand` appelle directement `file_get_contents` sans garde-fous. **Risque de passage de `false` à `TestAnalyzer`.**

**4. Gestion de la détection `REJECTED` fragile**
```php
if (str_contains($report, 'REJECTED')) {
```
> La détection du rejet repose sur la présence d'une chaîne littérale dans la réponse IA. Si le modèle reformule sa réponse, la détection échoue silencieusement. **Privilégier un format de réponse structuré (JSON)** avec un champ `status`.

**5. Timeout absent sur le Process Git**
```php
$process = new Process($gitArgs);
$process->run();
```
> Sur des dépôts volumineux, `git diff` sans timeout peut bloquer indéfiniment. **Ajouter `setTimeout()`** :
```php
$process = new Process($gitArgs);
$process->setTimeout(60);
```

---

### 🟡 Attention — Maintenabilité

**6. Double déclaration du nom de commande (`DocumentationGenerateCommand`)**
```php
protected static $defaultName = 'ai:doc:generate'; // déclaration statique
// ET dans configure() :
$this->setName('ai:doc:generate');                  // déclaration dynamique
```
> La double déclaration est redondante et source de désynchronisation potentielle. **Supprimer `$defaultName`** (déprécié en Symfony 6.1+) et conserver uniquement `setName()`.

**7. Chemin de sortie hardcodé**
```php
$filename = 'reports/AI_REVIEW.md';
```
> Le chemin est relatif au répertoire d'exécution courant (`cwd`). En CI/CD, ce répertoire peut varier. **Exposer ce chemin comme option CLI ou constante configurable.**

**8. Absence de limite de taille sur le contenu analysé**
```php
$io->note('Analyse du contenu (' . strlen($contentToReview) . ' caractères) par Claude...');
```
> Un fichier ou diff très volumineux peut dépasser les limites de tokens du modèle Claude, entraînant une erreur silencieuse dans le `catch`. **Ajouter une validation de taille maximum** avant l'appel IA.

---

### 🟢 Bonnes pratiques observées

- ✅ `declare(strict_types=1)` systématique sur les 3 fichiers
- ✅ Utilisation de `SymfonyStyle` pour une UX console cohérente
- ✅ `try/catch` global sur l'appel IA avec retour `FAILURE` propre
- ✅ `Process` instancié avec tableau d'arguments (protection injection)
- ✅ Vérifications de lisibilité complètes dans `CodeReviewCommand`
- ✅ Pattern multi-agents bien orchestré dans `TestGenerateCommand`

---

# 📈 Score de Clarté Technique : 97/100

> **-3 points** : La documentation ne couvre pas le namespace `Core/Ai/` (fichiers `ReviewAnalyzer`, `DocAnalyzer`, etc. non fournis), rendant l'analyse des dépendances partiellement spéculative sur les signatures de méthodes réelles.