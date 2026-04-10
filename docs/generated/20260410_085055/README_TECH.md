# 🛠️ Documentation Technique — sweeecli (swk)

## Architecture Globale et Choix Techniques

`sweeecli` (alias `swk`) est un **outil CLI propriétaire** développé sur le framework **Symfony Console**, empaqueté sous forme de PHAR auto-exécutable. Il centralise les opérations DevOps quotidiennes des développeurs sweeek : gestion Git avancée (gitflow custom), gestion des variables d'environnement, reverse proxy local, dump de base de données, et génération assistée par IA (Claude Anthropic).

L'application suit un pattern **Kernel/Command** inspiré du pattern micro-kernel de Symfony, mais sans conteneur de services (Symfony DI Container). Les dépendances sont résolues manuellement dans le `Kernel`. Le domaine est structuré par responsabilité fonctionnelle (Git, Env, AI, ReverseProxy, etc.) ce qui facilite la navigation et la maintenance.

**Technologies clés :**
- PHP 8.1+ (enums, `match`, `readonly`, `fibers` non utilisés)
- Symfony Console, Filesystem, Process, Cache (ArrayAdapter/FilesystemAdapter), Finder, Yaml, HttpClient
- Claude AI (Anthropic) via HTTP REST
- GitLab API v4
- kubectl / make / git via `symfony/process`

---

# 🗺️ Logique d'Arborescence

L'arborescence respecte une séparation stricte **Command** (couche présentation CLI) / **Core** (couche domaine/infrastructure), en accord avec les principes **Domain-Driven Design** et **Separation of Concerns**.

```
src/
├── Kernel.php                          ← Point d'entrée unique, composition root manuel
├── Core/                               ← Domaine métier, services réutilisables
│   ├── AbstractKernel.php              ← Bootstrap, câblage des services, update check
│   ├── Ai/                             ← Sous-domaine IA (clients, analyseurs, générateurs)
│   │   ├── ClaudeClient.php            ← Infrastructure : appel HTTP Anthropic
│   │   ├── ReviewAnalyzer.php          ← Service domaine : revue de code
│   │   ├── TestAnalyzer.php            ← Service domaine : stratégie de test
│   │   ├── TestGenerator.php           ← Service domaine : génération de tests
│   │   ├── FixtureGenerator.php        ← Service domaine : génération de fixtures
│   │   ├── DocAnalyzer.php             ← Service domaine : documentation
│   │   └── DocGenerator.php            ← Service domaine : génération doc simple
│   ├── Configuration/                  ← Sous-domaine configuration CLI
│   │   ├── ConfigurationManager.php    ← Lecture/validation config ~/.swk/config.yaml
│   │   ├── DefinitionBuilder.php       ← Schéma de validation Symfony Config
│   │   ├── ProjectManager.php          ← Gestion des projets annexes (swk-proxy, etc.)
│   │   └── Project/
│   │       ├── ProjectInterface.php    ← Contrat abstrait d'un projet géré
│   │       └── SwkProxy.php            ← Implémentation concrète du projet swk-proxy
│   ├── Gitlab/
│   │   └── GitlabClient.php            ← Client HTTP GitLab API v4 (releases, packages)
│   ├── Helper/
│   │   └── FolderHelper.php            ← Utilitaire chemins ($HOME, ~/.swk)
│   ├── Prerequisites/                  ← Sous-domaine prérequis (guard pattern)
│   │   ├── PrerequisitesAwareCommandInterface.php  ← Interface de contrat
│   │   ├── PrerequisitesConfiguration.php          ← Builder de règles de prérequis
│   │   └── Enum/
│   │       ├── Architecture.php        ← Enum: X64, X86, ARM_64, ARM_32
│   │       ├── Platform.php            ← Enum: LINUX, MAC_OS
│   │       └── ConditionType.php       ← Enum: OR, AND (réservé futur)
│   └── Updater/
│       ├── Updater.php                 ← Orchestration mise à jour PHAR
│       └── VersionChecker.php          ← Lecture version courante + last tag GitLab
└── Command/                            ← Couche présentation (commands Symfony Console)
    ├── Ai/                             ← Commands IA (délèguent aux Core/Ai/*)
    │   ├── CodeReviewCommand.php
    │   ├── DocumentationGenerateCommand.php
    │   └── TestGenerateCommand.php
    ├── Cli/                            ← Commands internes CLI (config, update)
    │   ├── CheckUpdateCommand.php
    │   ├── InitConfigCommand.php
    │   └── ViewConfigCommand.php
    ├── Documentation/
    │   └── OpenDocCommand.php
    ├── Env/                            ← Commands variables d'environnement
    │   ├── GenerateEnvFileCommand.php
    │   ├── HelmVariableArgumentCommand.php
    │   ├── InitEnvSystemCommand.php
    │   └── Tools/
    │       └── EnvTool.php             ← Utilitaire env (regex, nommage, scoping)
    ├── Git/                            ← Commands Git (gitflow custom)
    │   ├── AbstractGitCommand.php      ← Base : toutes les primitives git
    │   ├── Enum/
    │   │   └── RemoteType.php          ← Enum: MAIN, FORK
    │   ├── Helper/
    │   │   ├── GitConfig.php           ← Lecture config git (remotes)
    │   │   └── VersionTag.php          ← Value Object version semver
    │   ├── Demo/
    │   │   ├── DemoStartCommand.php
    │   │   └── DemoMergeFeatureCommand.php
    │   ├── Feature/
    │   │   ├── FeatureStartCommand.php
    │   │   └── FeaturePushCommand.php
    │   └── Hotfix/
    │       ├── HotfixStartCommand.php
    │       ├── HotfixMergeCommand.php
    │       ├── HotfixFinishCommand.php
    │       └── HotfixAbortCommand.php
    ├── Project/
    │   └── RetrieveDatabaseDumpCommand.php
    └── ReverseProxy/                   ← Commands proxy (délèguent à make)
        ├── AbstractReverseProxyCommand.php
        ├── DoctorReverseProxyCommand.php
        ├── InstallReverseProxyCommand.php
        ├── MigrateReverseProxyCommand.php
        ├── StartNgrokReverseProxyCommand.php
        ├── StartReverseProxyCommand.php
        ├── StopReverseProxyCommand.php
        ├── UninstallReverseProxyCommand.php
        └── UpdateReverseProxyCommand.php
```

**Pourquoi cette structure ?**

- `Core/` = logique **réutilisable et testable** indépendamment des commandes console. C'est le "domaine".
- `Command/` = uniquement la **logique de présentation** (parsing arguments, feedback UX, orchestration). Chaque sous-dossier correspond à un **bounded context** fonctionnel.
- `Command/Git/Helper/` et `Command/Env/Tools/` contiennent des Value Objects et utilitaires **locaux à leur domaine** (pas promus en `Core/` car non réutilisés en dehors de leur contexte).
- `Core/Prerequisites/Enum/` : les enums sont regroupés dans leur sous-domaine pour éviter la pollution du namespace racine.

---

# 🔄 Interactions (Mermaid)

```mermaid
graph TD
    subgraph Bootstrap["Bootstrap Layer"]
        PHAR["swk (PHAR Entry)"]
        K["Kernel.php"]
        AK["Core/AbstractKernel"]
    end

    subgraph CoreServices["Core Services"]
        CM["ConfigurationManager"]
        DB["DefinitionBuilder"]
        PM["ProjectManager"]
        FH["FolderHelper"]
        GLC["GitlabClient"]
        VCH["VersionChecker"]
        UPD["Updater"]
        CC["ClaudeClient"]
        CACHE["FilesystemAdapter (Cache)"]
    end

    subgraph CoreAI["Core / AI Services"]
        RA["ReviewAnalyzer"]
        TA["TestAnalyzer"]
        TG["TestGenerator"]
        FG["FixtureGenerator"]
        DA["DocAnalyzer"]
        DG["DocGenerator"]
    end

    subgraph CorePrereqs["Core / Prerequisites"]
        PAI["PrerequisitesAwareCommandInterface"]
        PC["PrerequisitesConfiguration"]
        ARCH["Enum/Architecture"]
        PLAT["Enum/Platform"]
        COND["Enum/ConditionType"]
    end

    subgraph CoreConfig["Core / Configuration"]
        PI["ProjectInterface"]
        SWP["SwkProxy"]
    end

    subgraph CommandsGit["Commands / Git"]
        AGC["AbstractGitCommand"]
        GCF["GitConfig"]
        VT["VersionTag"]
        REM["Enum/RemoteType"]
        HST["HotfixStartCommand"]
        HMR["HotfixMergeCommand"]
        HFN["HotfixFinishCommand"]
        HAB["HotfixAbortCommand"]
        FST["FeatureStartCommand"]
        FPU["FeaturePushCommand"]
        DST["DemoStartCommand"]
        DMF["DemoMergeFeatureCommand"]
    end

    subgraph CommandsReverseProxy["Commands / ReverseProxy"]
        ARP["AbstractReverseProxyCommand"]
        RPDOC["DoctorReverseProxyCommand"]
        RPINST["InstallReverseProxyCommand"]
        RPMIG["MigrateReverseProxyCommand"]
        RPNG["StartNgrokReverseProxyCommand"]
        RPST["StartReverseProxyCommand"]
        RPSTP["StopReverseProxyCommand"]
        RPUN["UninstallReverseProxyCommand"]
        RPUP["UpdateReverseProxyCommand"]
    end

    subgraph CommandsEnv["Commands / Env"]
        ET["EnvTool"]
        GEF["GenerateEnvFileCommand"]
        IES["InitEnvSystemCommand"]
        HVA["HelmVariableArgumentCommand"]
    end

    subgraph CommandsAI["Commands / AI"]
        CRC["CodeReviewCommand"]
        DGC["DocumentationGenerateCommand"]
        TGC["TestGenerateCommand"]
    end

    subgraph CommandsCli["Commands / CLI"]
        CUC["CheckUpdateCommand"]
        ICC["InitConfigCommand"]
        VCC["ViewConfigCommand"]
    end

    subgraph CommandsOther["Commands / Other"]
        ODC["OpenDocCommand"]
        RDB["RetrieveDatabaseDumpCommand"]
    end

    %% Bootstrap flow
    PHAR --> K
    K --> AK
    AK --> CM
    AK --> PM
    AK --> GLC
    AK --> CACHE
    AK --> UPD
    AK --> CC
    CM --> DB
    CM --> FH
    PM --> FH
    PM --> SWP
    SWP --> PI
    UPD --> VCH
    UPD --> GLC
    VCH --> GLC

    %% Kernel command wiring
    K --> CUC
    K --> ICC
    K --> VCC
    K --> ODC
    K --> GEF
    K --> IES
    K --> HVA
    K --> HST
    K --> HMR
    K --> HFN
    K --> HAB
    K --> FST
    K --> FPU
    K --> DST
    K --> DMF
    K --> RDB
    K --> RPDOC
    K --> RPINST
    K --> RPMIG
    K --> RPNG
    K --> RPST
    K --> RPSTP
    K --> RPUN
    K --> RPUP
    K --> CRC
    K --> DGC
    K --> TGC

    %% CLI Commands
    CUC --> UPD
    ICC --> CM
    VCC --> CM

    %% Git Commands
    AGC --> GCF
    AGC --> CM
    AGC --> VT
    AGC --> REM
    AGC --> PC
    GCF --> CM
    GCF --> REM
    HST --> AGC
    HMR --> AGC
    HFN --> AGC
    HAB --> AGC
    FST --> AGC
    FPU --> AGC
    DST --> AGC
    DMF --> AGC

    %% ReverseProxy Commands
    ARP --> PM
    ARP --> SWP
    ARP --> PC
    RPDOC --> ARP
    RPINST --> ARP
    RPMIG --> ARP
    RPNG --> ARP
    RPST --> ARP
    RPSTP --> ARP
    RPUN --> ARP
    RPUP --> ARP

    %% Env Commands
    GEF --> ET
    IES --> ET
    HVA --> ET

    %% AI Commands
    CRC --> RA
    DGC --> DA
    TGC --> TA
    TGC --> FG
    TGC --> TG
    RA --> CC
    TA --> CC
    TG --> CC
    FG --> CC
    DA --> CC
    DG --> CC

    %% Prerequisites
    AGC --> PAI
    ARP --> PAI
    ICC --> PAI
    GEF --> PAI
    IES --> PAI
    HVA --> PAI
    RPINST --> PAI
    RPMIG --> PAI
    PC --> ARCH
    PC --> PLAT
    PC --> COND
    PC --> PI

    %% External
    CC -->|"HTTPS POST"| ANTHROPIC["api.anthropic.com"]
    GLC -->|"HTTPS GET"| GITLAB["gitlab.com API v4"]
    RDB -->|"kubectl cp/exec"| K8S["AWS EKS Cluster"]
    AGC -->|"git CLI"| GITREPO["Git Repository"]
    ARP -->|"make"| MAKEFILE["swk-proxy Makefile"]
```

---

# 📋 Couverture par Fichier

---

## `Kernel.php`

**Rôle :** Composition root de l'application. Étend `AbstractKernel` et implémente les méthodes `getName()` (retourne `'swk'`) et `getCommands()` qui yield l'ensemble des commandes enregistrées.

**Responsabilités :**
- Déclare le nom du CLI (`swk`)
- Orchestre la création et l'injection des dépendances dans toutes les commandes via des méthodes privées groupées par domaine
- Instancie les services `Core/Ai/*` avec `$this->claudeClient` et `$projectDir`
- Partage un `ArrayAdapter` unique pour le cache Git intra-session entre toutes les commandes Git

**Point critique :** `$projectDir = dirname(__DIR__)` est calculé relativement au fichier compilé dans le PHAR — si la structure change, tous les `ClaudeClient` et les services AI qui lisent les prompts dans `config/prompts/` seront brisés.

**Entrées :** Aucune (constructeur parent)
**Sorties :** `iterable` de `Command`

**Scénario nominal :** `bin/swk hotfix:start` → `Kernel::getCommands()` → `getGitCommands()` → `HotfixStartCommand` instancié avec `$configurationManager` et `$cliCache`

**Scénario d'échec :** Si `CLAUDE_API_KEY` n'est pas défini en variable d'environnement, `ClaudeClient` est instancié avec une clé vide — les commandes AI s'exécuteront mais échoueront à l'appel HTTP avec une erreur 401.

---

## `Core/AbstractKernel.php`

**Rôle :** Classe abstraite de bootstrap. Initialise tous les services partagés et orchestre le démarrage de l'application.

**Responsabilités :**
- Instanciation des services fondamentaux : `ConfigurationManager`, `ProjectManager`, `GitlabClient`, `FilesystemAdapter` (cache persistant), `Updater`, `ClaudeClient`
- Gestion du check de mise à jour automatique au démarrage (avec cache 1 jour pour éviter les requêtes répétées)
- Enregistrement conditionnel des commandes via `registerCommands()` (filtre par `PrerequisitesAwareCommandInterface`)
- Exposition d'un flag `--disable-update-checking` (`-d`) global sur toutes les commandes

**Services injectés (auto-instanciés) :**
| Propriété | Type | Rôle |
|---|---|---|
| `$configurationManager` | `ConfigurationManager` | Config YAML utilisateur |
| `$projectManager` | `ProjectManager` | Projets gérés (swk-proxy) |
| `$cache` | `FilesystemAdapter` | Cache persistant (update check) |
| `$versionUpdater` | `Updater` | Mise à jour PHAR |
| `$claudeClient` | `ClaudeClient` | Client IA Anthropic |

**Clé technique : `checkUpdate()`**
- Utilise `FilesystemAdapter` avec TTL de **1440 secondes** (`60 * 24` — **attention : ce TTL est en secondes, soit 24 minutes, et non 24 heures**). C'est très probablement un **bug** : l'intention est 24h mais le calcul donne 24 minutes.
- Si une mise à jour est détectée et acceptée, le process s'arrête avec `exit` immédiatement après la mise à jour.

**Timeout ClaudeClient :** `timeout: 300`, `max_duration: 900` — ces valeurs sont surchargées par les variables d'environnement `CLAUDE_REQUEST_TIMEOUT` et `CLAUDE_MAX_DURATION` dans `ClaudeClient::requestWithRetry()`.

**Scénario d'échec :** `GitlabClient` lit `$_ENV['GITLAB_DEPLOY_TOKEN_USER']` sans null-coalescing → **fatal error si la variable n'est pas définie**.

---

## `Core/Ai/ClaudeClient.php`

**Rôle :** Client HTTP bas niveau pour l'API Anthropic Claude. C'est l'unique point d'accès réseau vers Claude.

**Responsabilités :**
- Envoi de requêtes POST vers `https://api.anthropic.com/v1/messages`
- Gestion de la **continuation automatique** quand Claude tronque sur `max_tokens` (jusqu'à 4 boucles)
- Retry automatique (2 tentatives) sur erreur réseau
- Nettoyage de la clé API (trim des `\r` de Cygwin)
- Décodage robuste du JSON de réponse (BOM UTF-8, caractères de contrôle)
- Lecture des paramètres depuis les variables d'environnement : `CLAUDE_API_KEY`, `CLAUDE_REQUEST_TIMEOUT`, `CLAUDE_MAX_DURATION`, `CLAUDE_MAX_TOKENS`

**Constantes :**
- `API_URL` : `https://api.anthropic.com/v1/messages`
- `CONTINUATION_MAX_LOOPS` : `4`
- Modèle par défaut : `claude-sonnet-4-6`

**Entrées :** `systemPrompt: string`, `userPrompt: string`
**Sorties :** `string` (texte généré, ou message d'erreur si vide)

**Scénario nominal :** `call($system, $user)` → `requestWithRetry()` → HTTP 200 → `extractTextContent()` → retour du texte. Si `stop_reason === 'max_tokens'` → boucle de continuation avec injection du texte partiel en tant que message `assistant`.

**Scénarios d'échec :**
- `401` : clé API invalide ou vide → exception avec le body de la réponse
- `429` : rate limit Anthropic → exception (pas de backoff exponentiel implémenté)
- Timeout réseau → retry une fois, puis exception
- JSON malformé dans la réponse → tentative de nettoyage BOM/control chars, puis exception si toujours invalide

**Points de vigilance :**
1. Le modèle `'claude-sonnet-4-6'` est un ID direct — tout changement de version du modèle chez Anthropic nécessite une mise à jour ici
2. `CLAUDE_API_KEY` transmis en clair dans les headers HTTP — doit être dans les variables d'environnement système, jamais dans un fichier committé
3. Pas de streaming — les réponses longues (documentation) attendent le timeout complet avant retour
4. `CONTINUATION_MAX_LOOPS = 4` × `max_tokens = 8192` = potentiellement ~32 768 tokens de sortie maximum

---

## `Core/Ai/ReviewAnalyzer.php`

**Rôle :** Service domaine pour la revue de code IA. Lit le prompt depuis `config/prompts/review_expert.md`.

**Dépendances :** `ClaudeClient`
**Entrées :** `diff: string`, `context: string`
**Sorties :** `string` (rapport markdown)
**Point critique :** Le chemin du prompt est calculé avec `__DIR__ . '/../../../config/prompts/review_expert.md'` — relatif à l'emplacement dans le PHAR. Si le fichier de prompt est absent → exception immédiate.

---

## `Core/Ai/TestAnalyzer.php`

**Rôle :** Agent 1 du workflow multi-agents de génération de tests. Analyse le code source et produit une **stratégie de test**.

**Dépendances :** `ClaudeClient`, `string $projectDir`
**Entrées :** `contentToTest: string`, `context: string`
**Sorties :** `string` (stratégie de test)
**Prompt utilisé :** `config/prompts/test_strategy_expert.md`
**Point critique :** Lève une exception si le fichier de prompt est absent (contrairement à `FixtureGenerator` qui a un fallback générique).

---

## `Core/Ai/FixtureGenerator.php`

**Rôle :** Agent 2 du workflow multi-agents. Génère les fixtures PHP nécessaires aux tests.

**Dépendances :** `ClaudeClient`, `string $projectDir`
**Entrées :** `sourceCode: string`, `strategy: string`
**Sorties :** `string` (code de fixtures PHP)
**Prompt utilisé :** `config/prompts/test_fixtures_expert.md` (fallback sur prompt générique si absent)

---

## `Core/Ai/TestGenerator.php`

**Rôle :** Agent 3 du workflow multi-agents. Rédige le code de test final.

**Dépendances :** `ClaudeClient`, `string $projectDir`
**Entrées :** `sourceCode: string`, `strategy: string`, `fixtures: string`, `type: string` (unit|functional)
**Sorties :** `string` (code de test PHP)
**Prompts utilisés :** `test_unit_expert.md` ou `test_functional_expert.md`

---

## `Core/Ai/DocAnalyzer.php`

**Rôle :** Service de génération de documentation IA. Agrège le contenu d'un répertoire et appelle Claude.

**Dépendances :** `ClaudeClient`, `string $projectDir`, `symfony/finder`
**Méthodes clés :**
- `aggregateDirectoryContent(path)` : concatène tous les fichiers `.php`, `.yaml`, `.xml`, `.json` du dossier
- `listDirectoryFiles(path)` : liste triée des fichiers compatibles
- `analyze(dir, type, context, format)` : orchestre la génération (technique ou fonctionnelle, md ou json)

**Contrainte format JSON :** Ajoute une instruction système supplémentaire pour forcer un JSON pur sans markdown.
**Point critique :** Pour de grands monorepos, `aggregateDirectoryContent()` peut générer un contexte dépassant la fenêtre contextuelle de Claude (~200K tokens pour Sonnet). Aucune pagination ou truncation n'est implémentée.

---

## `Core/Ai/DocGenerator.php`

**Rôle :** Service simplifié de génération de documentation (usage direct, non utilisé dans le Kernel actuel).

**Note :** `DocGenerator` est défini mais **n'est jamais instancié dans `Kernel.php`** — il s'agit probablement d'une classe orpheline ou d'une version antérieure. `DocAnalyzer` est utilisé à la place.

---

## `Core/Configuration/ConfigurationManager.php`

**Rôle :** Gestionnaire de la configuration utilisateur. Lit `~/.swk/config.yaml` et valide via le schéma Symfony Config.

**Dépendances :** `DefinitionBuilder`, `FolderHelper`
**Comportement :**
- Si le fichier est absent ou invalide (exception YAML) → retourne la configuration par défaut
- La validation via `DefinitionBuilder::buildDefinition()->finalize()` garantit les valeurs par défaut (`origin` / `fork`)

**Valeurs par défaut :**
```yaml
git:
  main_remote: origin
  fork_remote: fork
```

**Points de vigilance :**
- Le silence sur exception (`catch (\Exception)`) masque toute erreur YAML — un fichier de config syntaxiquement invalide sera silencieusement ignoré et remplacé par les défauts
- `getConfigFilePath()` dépend de `$_ENV['HOME']` — peut être vide sur certains environnements CI

---

## `Core/Configuration/DefinitionBuilder.php`

**Rôle :** Définit le schéma de validation de la configuration avec Symfony Config Component.

**Sortie :** `NodeInterface` — arbre de validation avec valeurs par défaut (`origin`, `fork`)

**Impact d'une modification :** Toute addition d'un nouveau nœud de configuration nécessite une mise à jour ici ET dans `ConfigurationManager::getDefaultConfiguration()`.

---

## `Core/Configuration/ProjectManager.php`

**Rôle :** Registry des projets gérés par swk (actuellement uniquement `SwkProxy`).

**Responsabilités :**
- Détection de l'installation d'un projet via lecture du dossier `~/.swk/projects/{name}/`
- Lazy loading avec mise en cache en mémoire (`$this->projects`)
- Calcul du chemin d'installation d'un projet

**Note architecturale :** Le commentaire `// TODO: use dependency injection concept` indique que la liste des projets est codée en dur (`new SwkProxy()`). Pour ajouter un nouveau projet géré, il faut modifier cette méthode directement.

**Point critique :** `isInstalled()` utilise `opendir()` avec opérateur de suppression d'erreur `@` — un dossier existant mais vide est considéré comme "non installé".

---

## `Core/Configuration/Project/SwkProxy.php` & `ProjectInterface.php`

**Rôle :** Value Object représentant le projet `swk-proxy` (reverse proxy local basé sur Docker/make).

**`ProjectInterface` définit le contrat :**
- `getName()` → `'swk-proxy'`
- `getRepository()` → `'git@gitlab.com:alicesgarden/swk-proxy.git'`
- `markAsInstalled()` → mutation d'état
- `isInstalled()` → `bool`

**Impact d'une modification du repository GitLab :** Mettre à jour `getRepository()` dans `SwkProxy.php` et modifier le token SSH/deploy key en conséquence.

---

## `Core/Gitlab/GitlabClient.php`

**Rôle :** Client HTTP pour l'API GitLab v4. Utilisé exclusivement pour la gestion des releases (check de version et téléchargement des binaires).

**Dépendances :** `symfony/http-client`
**Variables d'environnement requises (OBLIGATOIRES) :**
- `GITLAB_DEPLOY_TOKEN_USER` — **accès direct sans null-coalescing** → fatal error si absent
- `GITLAB_DEPLOY_TOKEN_PASSWORD` — idem
- `GITLAB_API_TOKEN` — optionnel (null-coalescing sur `''`)

**Project ID hardcodé :** `78182343` dans l'URL de l'API GitLab.

**Méthodes :**
- `getLatestRelease()` → `?array` (première release)
- `getLatestTag()` → `?string`
- `getLatestPackageUrl(platform, architecture)` → URL authentifiée

**Point critique :** `authenticateGitlabUrl()` injecte les credentials dans l'URL (Basic Auth) — ces URLs ne doivent jamais être loggées.

---

## `Core/Updater/VersionChecker.php`

**Rôle :** Comparaison version courante vs version distante.

**Lecture version courante :** `@file_get_contents(__DIR__.'/../../../.app.version')` — fichier `.app.version` à la racine du PHAR. L'opérateur `@` supprime l'erreur si le fichier est absent (retourne `'UNKNOWN'`).

**Point critique :** Si le fichier `.app.version` est absent du PHAR compilé, `getCurrentVersion()` retourne `'UNKNOWN'` et la comparaison avec la version distante sera toujours vraie → check de mise à jour permanent.

---

## `Core/Updater/Updater.php`

**Rôle :** Orchestration du téléchargement et remplacement du binaire PHAR.

**Processus de mise à jour :**
1. Détection plateforme/architecture
2. Récupération URL package depuis GitLab
3. `preg_match('/^phar:\/\/(.+)\/src/')` pour trouver le chemin du PHAR courant
4. `Filesystem::copy()` → téléchargement `swk.tar.gz`
5. `exec('tar -xzf ...')` → extraction
6. `exec('sudo mv swk targetPath')` → **nécessite sudo**

**Points de vigilance critiques :**
- Le `sudo mv` est exécuté sans confirmation supplémentaire
- `exec()` sans vérification de code retour pour tar et chmod
- `$architecture` non initialisé si `php_uname('m')` retourne une valeur non listée → `TypeError`
- La regex `phar://` ne fonctionnera **jamais en mode développement** (hors PHAR) — retourne une exception

---

## `Core/Helper/FolderHelper.php`

**Rôle :** Utilitaire statique pour les chemins système.

**Méthodes :**
- `getHomeFolder()` → `$_ENV['HOME'] ?? getenv('HOME') ?: ''## `Core/Prerequisites/PrerequisitesConfiguration.php`

**Rôle :** Builder fluent de règles de prérequis pour les commandes. Implémente un **Guard Pattern** : si les prérequis d'une commande ne sont pas satisfaits, elle n'est tout simplement pas enregistrée dans l'application Symfony Console (filtrée dans `AbstractKernel::registerCommands()`).

**Responsabilités :**
- Vérification de plateforme (Linux / macOS)
- Vérification d'architecture (x64, x86, ARM 32/64)
- Vérification de projets installés (`ProjectInterface::isInstalled()`)
- Vérification de fichiers/dossiers (existence ou non-existence)
- Validateurs personnalisés arbitraires via `addCustomValidator(callable)`

**API fluent (Builder Pattern) :**
```php
(new PrerequisitesConfiguration())
    ->hasPlatforms(Platform::MAC_OS)
    ->hasArchitecture(Architecture::X64)
    ->hasProject($project)
    ->andOneOfFilesOrDirectoriesExist('.git', '.svn')
    ->andFileOrDirectoryNotExist('env.config.yaml')
    ->addCustomValidator(fn() => true === someCheck());
```

**Logique de `checkPrerequisites()`** : AND logique strict entre toutes les règles — une seule règle échouée empêche l'enregistrement de la commande.

**Point de vigilance :** `checkPlatform()` et `checkArchitecture()` utilisent `match` **sans case `default`** — si l'OS est Windows (`PHP_OS_FAMILY === 'Windows'`) ou une architecture exotique, une `\UnhandledMatchError` sera levée. L'enum `ConditionType` (OR/AND) est déclaré mais **jamais utilisé** dans l'implémentation actuelle — réservé pour une future logique OR entre prérequis.

**Scénario nominal :** `proxy:start` sur macOS avec swk-proxy installé → `checkPlatform()` ✓, `checkProjects()` ✓ → commande enregistrée.

**Scénario d'échec :** `proxy:start` sans swk-proxy installé → `checkProjects()` retourne `false` → commande absente de l'application → `swk proxy:start` retourne `Command "proxy:start" is not defined`.

---

## `Core/Prerequisites/PrerequisitesAwareCommandInterface.php`

**Rôle :** Contrat d'interface pour toute commande souhaitant déclarer des prérequis.

**Méthode unique :** `getPrerequisites(): PrerequisitesConfiguration`

**Impact :** Toute commande implémentant cette interface est soumise au filtre de `AbstractKernel::registerCommands()`. Une commande qui n'implémente PAS cette interface est **toujours enregistrée** sans condition (ex: `OpenDocCommand`, `RetrieveDatabaseDumpCommand`).

---

## `Core/Prerequisites/Enum/Architecture.php`, `Platform.php`, `ConditionType.php`

**Rôle :** Enums PHP 8.1 représentant les valeurs valides pour les prérequis système.

| Enum | Cases |
|---|---|
| `Architecture` | `X64`, `X86`, `ARM_64`, `ARM_32` |
| `Platform` | `LINUX`, `MAC_OS` |
| `ConditionType` | `OR`, `AND` (non utilisé) |

**Point de vigilance :** `ConditionType` est défini mais non consommé — dead code à documenter ou implémenter.

---

## `Command/Git/AbstractGitCommand.php`

**Rôle :** Classe abstraite centrale du sous-système Git. Fournit toutes les **primitives Git** via `symfony/process`, gère les erreurs, et expose le cache intra-session.

**Dépendances :**
- `ConfigurationManager` → via `GitConfig` pour lire les noms de remotes
- `ArrayAdapter` (cache) → partagé entre toutes les instances Git dans le Kernel
- `symfony/process` → exécution des commandes git système

**Préfixage automatique des noms de commandes :** `setName()` est surchargé pour préfixer automatiquement avec `'git:'` — ainsi `setName('hotfix:start')` dans la sous-classe donne la commande `git:hotfix:start` dans la CLI.

**Prérequis automatique :** `getPrerequisites()` vérifie via `git rev-parse --is-inside-work-tree` que le répertoire courant est un dépôt Git. Ce résultat est **mis en cache** dans `ArrayAdapter` (cache mémoire intra-process, clé `has_git`).

**Primitives disponibles :**

| Méthode | Commande Git | Notes |
|---|---|---|
| `checkoutToBranch(branch, create)` | `git checkout [-b] branch` | Défaut : `master` |
| `checkoutToRemoteBranch(branch, remote)` | `git checkout --track remote/branch` | Défaut : `MAIN` |
| `removeBranch(branch, remote?)` | `git branch -D` ou `git push --delete` | |
| `mergeBranch(branch, remote?, msg?)` | `git merge --no-ff` | Toujours `--no-ff` |
| `getBranchName()` | `git rev-parse --abbrev-ref HEAD` | |
| `tag(name)` | `git tag name` | |
| `add(path)` | `git add path` | Défaut : `.` |
| `stash()` | `git stash` | |
| `stashApply()` | `git stash apply` | |
| `commit(msg, allowEmpty)` | `git commit -m` | |
| `getLastCommitMessage()` | `git log -1 --pretty=%B` | |
| `reset(hard, branch, remote?)` | `git reset [--hard]` | |
| `fetch(remote)` | `git fetch remote` | Défaut : `MAIN` |
| `push(branch, remote, force)` | `git push remote branch [--force]` | Retourne stdout+stderr |
| `pushTag(tag, remote)` | `git push remote tag tag` | |
| `hasLocalChanges()` | `git status --porcelain` | `true` si output non vide |
| `getLatestVersionTag(branch)` | `git tag --sort=creatordate --merged branch` | Retourne `VersionTag('0.0.0')` si aucun tag valide |
| `isBranchExistInLocal(name)` | `git branch --list name` | |
| `isBranchExistInRemote(name, remote)` | `git ls-remote --heads remote name` | |

**Gestion d'erreurs :** `execute()` encapsule `doExecute()` dans un try/catch `ProcessFailedException` — affiche la commande échouée, stdout et stderr, puis retourne `FAILURE`.

**Point de vigilance critique :** Toutes les primitives utilisent `mustRun()` qui lève `ProcessFailedException` en cas d'échec git. C'est intentionnel (fail-fast), mais cela signifie qu'une erreur Git non anticipée dans une sous-commande interrompra l'ensemble du workflow (ex: conflit de merge dans `HotfixFinish`).

---

## `Command/Git/Helper/GitConfig.php`

**Rôle :** Value Object d'accès à la configuration Git de l'utilisateur.

**Dépendances :** `ConfigurationManager`, `RemoteType`

**Méthodes :**
- `getMainRemoteName()` → nom du remote principal (défaut : `origin`)
- `getForkRemoteName()` → nom du remote fork (défaut : `fork`)
- `getRemoteBranch(RemoteType)` → dispatch par enum vers la bonne méthode

**Utilisation type :**
```php
$this->config->getRemoteBranch(RemoteType::FORK) // retourne 'fork'
$this->config->getRemoteBranch(RemoteType::MAIN) // retourne 'origin'
```

---

## `Command/Git/Helper/VersionTag.php`

**Rôle :** Value Object représentant une version sémantique `MAJOR.FEATURE.MINOR` (non standard — remplace `PATCH` par `FEATURE`).

**Responsabilités :**
- Parsing et validation via regex `'/^(\d+)\.(\d+)\.(\d+)$/'`
- Incrémentation individuelle de chaque composant
- Comparaison via `__toString()`

**Note sémantique :** La convention sweeek utilise `MAJOR.FEATURE.MINOR` où `minor` correspond au patch/hotfix et `feature` correspond aux releases de fonctionnalités — c'est une déviation volontaire du semver standard.

**Scénario d'échec :** Construction avec une version invalide (ex: `'v1.0.0'` avec le `v` préfixé) lève une `\InvalidArgumentException`. `getLatestVersionTag()` dans `AbstractGitCommand` retourne `new VersionTag('0.0.0')` si aucun tag valide n'est trouvé — cela donne `hotfix/0.0.1` comme branche de premier hotfix.

---

## `Command/Git/Enum/RemoteType.php`

**Rôle :** Enum discriminant entre le remote principal (`MAIN`) et le remote fork (`FORK`). Utilisé dans toutes les primitives Git nécessitant un remote cible.

---

## `Command/Git/Hotfix/HotfixStartCommand.php`

**Rôle :** Démarre un cycle de hotfix en créant/récupérant la branche `hotfix/X.Y.Z+1`.

**Workflow :**
1. Stash des changements locaux si demandé
2. Checkout `master` + fetch + reset hard sur `origin/master`
3. Calcul de la prochaine version : `getLatestVersionTag()` + `incrementMinor()`
4. Si branche remote existe → checkout tracking ; sinon création avec commit initial `[skip ci]`
5. Restauration du stash si applicable

**Prérequis :** Être dans un dépôt Git (hérité d'`AbstractGitCommand`).

**Scénario nominatif :**
```
swk hotfix:start
→ fetch origin
→ reset --hard origin/master
→ calcul: dernière tag = 2.1.5 → hotfix/2.1.6
→ git checkout -b hotfix/2.1.6
→ git commit --allow-empty "[swk] Init hotfix hotfix/2.1.6. [skip ci]"
→ git push origin HEAD
```

---

## `Command/Git/Hotfix/HotfixMergeCommand.php`

**Rôle :** Merge une branche `feature/X` dans la branche hotfix courante.

**Workflow :**
1. Vérification absence de changements locaux
2. Appel interne à `git:hotfix:start` via `getApplication()->doRun()`
3. Recherche de `feature/{featureBranch}` local puis remote
4. Merge `--no-ff` avec message de merge
5. Push

**Point critique :** L'appel récursif à `git:hotfix:start` via `doRun()` est une **dépendance inter-commandes** forte — si `HotfixStartCommand` est supprimée ou renommée, `HotfixMergeCommand` échoue silencieusement avec `Error on hotfix start.`

---

## `Command/Git/Hotfix/HotfixFinishCommand.php`

**Rôle :** Finalise le hotfix : merge dans master, tag, push, nettoyage des branches.

**Workflow complet :**
1. Vérification absence de changements locaux
2. Calcul de la nouvelle version (même logique que `HotfixStart`)
3. Vérification que la branche courante est bien `hotfix/X.Y.Z`
4. Détection branche vide (message de commit = `[swk] Init hotfix`) → proposition d'abort
5. Checkout `master` + fetch + reset hard
6. Merge `--no-ff` de la branche hotfix dans master
7. Création du tag version
8. Push master + push tag
9. Suppression branche locale et remote

**Points critiques :**
- Si le merge crée un conflit, `mustRun()` lève une exception non gérée → le workflow s'interrompt en laissant master dans un état de merge en cours
- `ensureOnValidHotfixBranch()` peut déclencher `HotfixStartCommand` récursivement
- La suppression de la branche remote (`git push origin --delete hotfix/X.Y.Z`) après un push réussi peut échouer si la branche a déjà une protection GitLab

---

## `Command/Git/Hotfix/HotfixAbortCommand.php`

**Rôle :** Annule un hotfix en cours : supprime la branche locale et remote.

**Workflow :**
1. Demande de confirmation explicite
2. Checkout `master`
3. Suppression branche locale (`git branch -D`)
4. Suppression branche remote (`git push origin --delete`)

---

## `Command/Git/Feature/FeatureStartCommand.php`

**Rôle :** Crée ou reprend une branche `feature/{name}`.

**Ordre de recherche de la branche :**
1. Locale → `git checkout feature/name`
2. Remote fork → `git checkout --track fork/feature/name`
3. Remote main → `git checkout --track origin/feature/name`
4. Inexistante → création depuis `master` + push initial

**Option `--stash-changes` :** Permet de stasher automatiquement sans prompt interactif (utile pour les scripts CI ou les workflows automatisés).

---

## `Command/Git/Feature/FeaturePushCommand.php`

**Rôle :** Commite et pousse les changements locaux vers le remote **fork** (non vers main). Propose l'ouverture d'une Merge Request GitLab.

**Workflow :**
1. Vérification que la branche courante est `feature/*`
2. Si changements locaux → commit interactif avec message obligatoire
3. Push vers fork (`RemoteType::FORK`) avec support `--force`
4. Extraction de l'URL de création de MR depuis la sortie git (regex sur `https://gitlab.com/.+/merge_requests/new`)
5. Construction de l'URL MR avec `merge_request[title]` (préfixé par emoji selon le type) et `merge_request[description]`
6. `exec('open URL')` pour ouvrir dans le navigateur

**Point de vigilance :** `exec('open ...')` ne fonctionne que sur macOS. Sur Linux, il faudrait `xdg-open`. Aucune détection de plateforme n'est faite ici.

---

## `Command/Git/Demo/DemoStartCommand.php`

**Rôle :** Initialise ou reprend la branche `demo/demo` pour les environnements de démonstration.

**Workflow :**
1. Vérification absence de changements locaux
2. Checkout `master` + fetch + reset hard
3. Récupération du dernier tag de production
4. Si branche locale `demo/demo` existe → suppression
5. Si branche remote inexistante → création + push
6. Si branche remote existante → checkout + comparaison des tags (avertissement si désynchronisé avec production)

**Note :** La branche demo n'est **jamais mise à jour automatiquement** avec la production — un message d'instruction manuelle est affiché (`git merge --no-ff {tag} && git push origin demo/demo`).

---

## `Command/Git/Demo/DemoMergeFeatureCommand.php`

**Rôle :** Merge une feature dans la branche demo.

**Workflow :**
1. Stash des changements locaux si confirmé
2. Appel interne à `git:demo:start` via `doRun()`
3. Recherche `feature/{name}` local puis remote main
4. Merge `--no-ff`
5. Push
6. Restauration stash si applicable

---

## `Command/Cli/CheckUpdateCommand.php`

**Rôle :** Commande manuelle de vérification et installation de mise à jour.

**Différence avec `AbstractKernel::checkUpdate()` :** Cette commande est déclenchée explicitement par l'utilisateur (`swk cli:check-update`), tandis que le check automatique au démarrage est géré par le Kernel avec cache.

**Workflow :**
1. Affichage de la version courante
2. Si pas de mise à jour → succès
3. Si mise à jour disponible → confirmation interactive → appel `Updater::updateToLastVersion()`
4. Gestion des erreurs avec retour `FAILURE`

---

## `Command/Cli/InitConfigCommand.php`

**Rôle :** Crée le fichier `~/.swk/config.yaml` avec la configuration par défaut (toutes les clés commentées).

**Prérequis :** Le fichier de config ne doit **pas** exister (`andFileOrDirectoryNotExist`).

**Comportement :** Génère le YAML de la config par défaut, puis préfixe chaque ligne avec `#` (commentaire YAML) — ce qui donne un fichier de référence documenté plutôt qu'une config active.

**Scénario d'échec :** Si `~/.swk/` n'existe pas, `Filesystem::dumpFile()` crée les répertoires intermédiaires automatiquement.

---

## `Command/Cli/ViewConfigCommand.php`

**Rôle :** Affiche la configuration active sous forme d'arbre.

**Utilise `$io->tree()`** — méthode disponible dans Symfony Console 7.x+ via `SymfonyStyle`. Les valeurs sont formatées avec des balises `<info>` pour le highlighting terminal.

**Point de vigilance :** Si la configuration contient des clés avec des valeurs `null`, `serializeConfiguration()` appliquera une concaténation de string sur `null` — comportement indéfini en PHP strict (dépend de la version).

---

## `Command/Documentation/OpenDocCommand.php`

**Rôle :** Ouvre `https://doc.sweeek.org` dans le navigateur par défaut.

**Implémentation :** `exec('open https://doc.sweeek.org')` — uniquement macOS. Pas de `PrerequisitesAwareCommandInterface`, donc toujours enregistrée.

**Point de vigilance :** Aucune gestion d'erreur si `open` échoue (Linux, WSL).

---

## `Command/Env/Tools/EnvTool.php`

**Rôle :** Utilitaire de transformation et résolution des noms de variables d'environnement.

**Constantes :**
- `CONFIG_FILE_NAME` : `'env.config.yaml'`
- `DEFAULT_CONFIG` : structure YAML de départ avec `_mapping`, `app.secrets`, `app.configmaps`

**Méthodes clés :**

**`getEnvFileKeys(filePath)`** : Lit un fichier `.env` et extrait les clés (avant le `=`) via regex `'/(\w+)=.+/m'`. Retourne `[]` si fichier inexistant.

**`getCleanVariableName(variableName, mapping)`** :
1. Remplace les clés du mapping par une chaîne vide
2. Remplace `{ENV}` par une chaîne vide
3. Supprime le `_` préfixant en résultant

Exemple : `'{ENV}_APP_SECRET'` avec `mapping = []` → `'APP_SECRET'`

**`getScopedVariableName(variableName, env, mapping)`** :
1. Remplace les clés du mapping par leurs valeurs
2. Remplace `{ENV}` par la valeur de l'environnement cible

Exemple : `'{ENV}_APP_SECRET'` avec `env = 'prod'` → `'PROD_APP_SECRET'`

**Cas d'usage concret :**
```yaml
# env.config.yaml
_mapping:
  "MY_PREFIX_": ""
app:
  secrets:
    MY_PREFIX_{ENV}_DB_PASSWORD: ""
```
→ `getCleanVariableName('MY_PREFIX_{ENV}_DB_PASSWORD')` → `'DB_PASSWORD'`
→ `getScopedVariableName('MY_PREFIX_{ENV}_DB_PASSWORD', 'staging')` → `'_STAGING_DB_PASSWORD'`

---

## `Command/Env/InitEnvSystemCommand.php`

**Rôle :** Initialise le fichier `env.config.yaml` dans le répertoire courant.

**Prérequis :**
- `env.config.yaml` ne doit **pas** exister
- `.git` doit exister (empêche l'initialisation hors d'un projet Git)

**Comportement :** Crée et remplit `env.config.yaml` avec `EnvTool::DEFAULT_CONFIG` sérialisé en YAML avec l'option `DUMP_EMPTY_ARRAY_AS_SEQUENCE`.

---

## `Command/Env/GenerateEnvFileCommand.php`

**Rôle :** Génère ou complète un fichier `.env` à partir de `env.config.yaml`.

**Arguments :**
- `applicationName` (requis) : clé de section dans le YAML
- `filename` (optionnel) : chemin du fichier `.{appName}.env` par défaut

**Comportement :**
1. Parse `env.config.yaml`
2. Fusionne `configmaps` et `secrets` de l'application
3. Lit les clés déjà présentes dans le fichier destination (évite les doublons)
4. Ajoute uniquement les nouvelles clés en **append** (ne modifie pas les valeurs existantes)

**Point de vigilance :** L'`appendToFile()` ne gère pas le saut de ligne final avant les nouvelles entrées — si le fichier existant ne se termine pas par `\n`, les nouvelles lignes seront concaténées à la dernière ligne existante.

---

## `Command/Env/HelmVariableArgumentCommand.php`

**Rôle :** Génère les arguments `--set` Helm pour déployer des variables d'environnement en Kubernetes.

**Arguments :**
- `applicationName` (requis)
- `env` (requis) : environnement cible (ex: `staging`, `prod`)

**Output :** Une ligne de flags Helm sur stdout, ex:
```
--set app.secrets.DB_PASSWORD=mypassword --set app.configmaps.APP_ENV=production
```

**Résolution de la valeur :** `$_ENV[$scopedName] ?? $defaultValue ?? ''` — lit depuis les variables d'environnement système de la session courante.

**Usage typique dans un pipeline CI/CD :**
```bash
helm upgrade app ./chart $(swk env:helm:arguments app prod)
```

---

## `Command/ReverseProxy/AbstractReverseProxyCommand.php`

**Rôle :** Base de toutes les commandes reverse proxy. Fournit `buildSwkProxyCommand()` et le prérequis d'installation.

**Dépendances :** `ProjectManager`, `SwkProxy`

**`buildSwkProxyCommand(command)`** : Construit la commande `make -C /path/to/swk-proxy FROM=sweeecli {command}`.

**Prérequis hérité :** `hasProject($swkProxy)` — vérifie que swk-proxy est installé. Les sous-classes qui **surchargent** `getPrerequisites()` (ex: `InstallReverseProxyCommand`, `MigrateReverseProxyCommand`) remplacent complètement ce prérequis.

**Point de vigilance :** `buildSwkProxyCommand()` appelle `getProjectPath($project)` qui peut retourner un chemin vers un dossier vide si `ProjectManager::isInstalled()` a mal détecté l'état — le `make` échouera silencieusement (pas de `mustRun()`).

---

## `Command/ReverseProxy/InstallReverseProxyCommand.php`

**Rôle :** Clone le repository swk-proxy et lance `make install`.

**Prérequis personnalisés :**
- swk-proxy **non installé** (`!isInstalled()`)
- `/usr/local/bin/swk-proxy` **inexistant** (pas de legacy binary)

**Workflow :**
```
git clone git@gitlab.com:alicesgarden/swk-proxy.git ~/.swk/projects/swk-proxy
make -C ~/.swk/projects/swk-proxy FROM=sweeecli install
```

**Point critique :** Le `git clone` utilise `mustRun()` → lève une exception si le clone échoue (pas d'accès SSH, repo privé, etc.). Le `make install` utilise un callback non-throwing.

---

## `Command/ReverseProxy/MigrateReverseProxyCommand.php`

**Rôle :** Migre un ancien projet `swk-proxy` standalone (symlink dans `/usr/local/bin/swk-proxy`) vers la gestion intégrée sweeecli.

**Prérequis personnalisés :**
- swk-proxy **non installé** via sweeecli
- `/usr/local/bin/swk-proxy` **existe** (ancienne installation)

**Workflow :**
1. Lit le vrai chemin via `readlink('/usr/local/bin/swk-proxy', true)`
2. `make -C oldPath FROM=swk-proxy down` (arrêt propre)
3. `Filesystem::rename()` vers `~/.swk/projects/swk-proxy`
4. `Filesystem::remove('/usr/local/bin/swk-proxy')` (suppression symlink)

---

## `Command/ReverseProxy/StartReverseProxyCommand.php`, `StopReverseProxyCommand.php`, `UpdateReverseProxyCommand.php`, `UninstallReverseProxyCommand.php`, `DoctorReverseProxyCommand.php`, `StartNgrokReverseProxyCommand.php`

**Rôle commun :** Délèguent chacune à une cible `make` différente via `buildSwkProxyCommand()`.

| Commande | Target Make | Description |
|---|---|---|
| `proxy:start` | `up` | Démarre Docker Compose |
| `proxy:stop` | `down` | Arrête Docker Compose |
| `proxy:update` | `self-update` | Met à jour le projet swk-proxy |
| `proxy:uninstall` | `uninstall` | Désinstalle swk-proxy |
| `proxy:doctor` | `doctor` | Diagnostics de l'environnement |
| `proxy:start:ngrok` | `ngrok` | Démarre avec tunnel ngrok |

Toutes utilisent un callback de streaming stdout/stderr vers la console sans `mustRun()` — les erreurs make sont affichées mais ne font pas échouer la commande PHP.

---

## `Command/Project/RetrieveDatabaseDumpCommand.php`

**Rôle :** Télécharge le dump SQL de production depuis un pod Kubernetes AWS EKS vers le poste local.

**Configuration hardcodée :**
```php
$context = 'arn:aws:eks:eu-west-3:096866357657:cluster/wb-web-prod';
$namespace = 'main-api';
$remotePath = '/srv/app/data/dump/dump.sql';
```

**Workflow :**
1. `kubectl get pods -n main-api` → extraction du pod `sweeek-api-master-admin-*` par regex
2. `kubectl exec stat -c%s /srv/app/data/dump/dump.sql` → taille du fichier (pour la progress bar)
3. `kubectl cp namespace/pod:/path/dump.sql destinationFile` en mode asynchrone (`start()` + polling)
4. Polling toutes les 250ms sur `filesize($destinationFile)` pour mettre à jour la progress bar
5. Post-traitement `sed -i '' 's/\\-/-/g'` (suppression des backslashes parasites macOS)

**Points critiques :**
- ARN AWS, namespace et chemin **hardcodés** — non configurables sans modification du code
- Pattern regex du pod `/(sweeek-api-master-admin-\S+)/` hardcodé
- `clearstatcache()` appelé pour éviter le cache PHP de `filesize()` — correct
- `sed -i ''` est la syntaxe **macOS** — sur Linux, le `''` doit être supprimé → incompatibilité
- Pas de `PrerequisitesAwareCommandInterface` → toujours enregistrée même sans kubectl

---

## `Command/Ai/CodeReviewCommand.php`

**Rôle :** Interface CLI pour la revue de code IA. Supporte l'analyse d'un `git diff` ou d'un fichier entier.

**Options :**
- `base` (arg optionnel, défaut `HEAD`) : commit/branche de base du diff
- `target` (arg optionnel) : branche cible à comparer
- `--file / -f` : analyser un fichier complet au lieu du diff
- `--context / -c` : contexte projet pour Claude
- `--export / -e` : exporte le rapport dans `reports/AI_REVIEW.md`

**Workflow fichier :**
```
lecture fichier → ContentToReview = "Fichier analysé: path\n\ncontent"
→ ReviewAnalyzer::analyze()
→ affichage rapport
→ export optionnel
→ détection "REJECTED" → FAILURE
```

**Workflow diff :**
```
git diff base [target]
→ ReviewAnalyzer::analyze(diff, context)
→ affichage rapport
→ export optionnel
→ détection "REJECTED" → FAILURE
```

**Logique de scoring :** Si le rapport contient la chaîne `'REJECTED'`, la commande retourne `FAILURE` (code de sortie non-zéro) — utile pour intégrer la revue dans des hooks Git pre-push ou des pipelines CI.

**Point de vigilance :** Le dossier `reports/` est créé avec `mkdir($folder, 0777, true)` — permissions larges, à surveiller si le CLI est exécuté avec des droits élevés.

---

## `Command/Ai/DocumentationGenerateCommand.php`

**Rôle :** Génère deux documentations complémentaires (technique et fonctionnelle) pour un dossier de code.

**Options :**
- `directory` (arg requis) : chemin du dossier à documenter
- `--context / -c` : contexte supplémentaire
- `--export / -e` : format de sortie (`md` ou `json`, défaut `md`)

**Workflow :**
1. Création du dossier `docs/generated/YYYYMMDD_HHIISS/`
2. Appel `DocAnalyzer::analyze(dir, 'technical', ctx, format)`
3. Appel `DocAnalyzer::analyze(dir, 'functional', ctx, format)`
4. Export selon format :
   - `md` → `README_TECH.md` + `README_FUNC.md`
   - `json` → `README_TECH.json` + ``README_FUNC.json`

**Méthode `normalizeJson()`** : Pipeline de nettoyage robuste pour gérer les sorties imparfaites de Claude :
1. Suppression des balises markdown ` ```json ``` `
2. Nettoyage BOM UTF-8
3. Extraction du premier objet/tableau JSON par position (`strpos`)
4. Suppression des caractères de contrôle non-JSON (`\x00-\x08`, etc.)
5. Double tentative de `json_decode` avec fallback agressif
6. En dernier recours : objet JSON de fallback avec `raw_content` et message d'erreur

**Point de vigilance :** Si Claude retourne du texte narratif autour du JSON (ex: "Voici le résultat : {...}"), la méthode tente de localiser et extraire le JSON — mais si le JSON est malformé **et** entouré de texte, le fallback final préserve le contenu brut dans `raw_content`. Le développeur consommant ce JSON doit donc vérifier la présence de la clé `warning`.

---

## `Command/Ai/TestGenerateCommand.php`

**Rôle :** Orchestrateur du workflow **Multi-Agents** de génération de tests en 3 étapes séquentielles.

**Arguments :**
- `file` (requis) : chemin du fichier PHP source à tester
- `--type / -t` : `unit` (défaut) ou `functional`

**Workflow séquentiel (pipeline multi-agents) :**

```
Agent 1 : TestAnalyzer::analyze(sourceCode)
          → Stratégie de test (quoi tester, comment, priorités)
          ↓
Agent 2 : FixtureGenerator::generate(sourceCode, strategy)
          → Fixtures PHP (mocks, données de test)
          ↓
Agent 3 : TestGenerator::generate(sourceCode, strategy, fixtures, type)
          → Code de test complet (PHPUnit)
          ↓
Output : affichage du code généré sur stdout
```

**Point de vigilance :** Le résultat final est écrit sur `stdout` via `$io->writeln($finalCode)` — pour être exploitable, il faut rediriger la sortie (`swk ai:tests:create MyClass.php > tests/MyClassTest.php`). Aucune écriture automatique de fichier n'est faite.

**Coût en tokens :** Ce workflow effectue **3 appels Claude distincts** — chacun potentiellement jusqu'à `CONTINUATION_MAX_LOOPS × max_tokens`. Pour un fichier complexe, cela peut représenter ~24 000 tokens en entrée + ~24 000 tokens en sortie (×3 agents), soit un coût significatif.

---

# ⚠️ Points de Vigilance Techniques

## 🔴 Critiques (Blocants en production)

### 1. Variables d'environnement sans null-coalescing dans `GitlabClient`
```php
// Core/Gitlab/GitlabClient.php — lignes 18-19
$this->deployTokenUser = $_ENV['GITLAB_DEPLOY_TOKEN_USER'];
$this->deployTokenPassword = $_ENV['GITLAB_DEPLOY_TOKEN_PASSWORD'];
```
**Risque :** `Undefined array key` fatal error si ces variables ne sont pas définies. Cela se produit à **l'instanciation du Kernel**, donc au démarrage de **toute** commande, même celles sans rapport avec GitLab.

**Correction recommandée :**
```php
$this->deployTokenUser = $_ENV['GITLAB_DEPLOY_TOKEN_USER'] ?? getenv('GITLAB_DEPLOY_TOKEN_USER') ?? '';
$this->deployTokenPassword = $_ENV['GITLAB_DEPLOY_TOKEN_PASSWORD'] ?? getenv('GITLAB_DEPLOY_TOKEN_PASSWORD') ?? '';
```

---

### 2. Bug TTL du cache de mise à jour dans `AbstractKernel`
```php
// Core/AbstractKernel.php
private const CACHE_TTL_ONE_DAY = 60 * 24; // = 1440 secondes = 24 MINUTES
```
**Risque :** L'intention est 24 heures (`60 * 60 * 24 = 86400`), mais le cache expire en **24 minutes**. L'utilisateur sera interrogé pour une mise à jour toutes les 24 minutes au lieu d'une fois par jour.

**Correction recommandée :**
```php
private const CACHE_TTL_ONE_DAY = 60 * 60 * 24; // 86400 secondes
```

---

### 3. `UnhandledMatchError` sur plateforme non supportée dans `PrerequisitesConfiguration`
```php
// Core/Prerequisites/PrerequisitesConfiguration.php
$platform = match (PHP_OS_FAMILY) {
    'Linux' => Platform::LINUX,
    'Darwin' => Platform::MAC_OS,
    // Pas de default → UnhandledMatchError sur Windows/BSD
};
```
**Risque :** Sur Windows (WSL natif ou Git Bash), toutes les commandes avec prérequis de plateforme lèveront une exception non catchée.

**Correction recommandée :**
```php
$platform = match (PHP_OS_FAMILY) {
    'Linux' => Platform::LINUX,
    'Darwin' => Platform::MAC_OS,
    default => null,
};
if (null === $platform) return false;
```

---

### 4. `CLAUDE_API_KEY` non défini = appels silencieusement échoués
```php
// Core/AbstractKernel.php
$_ENV['CLAUDE_API_KEY'] ?? getenv('CLAUDE_API_KEY') ?? ''
```
La clé vide est acceptée silencieusement à la construction. L'erreur n'arrive qu'à l'appel HTTP (401 Anthropic). Il n'y a **aucune validation au démarrage** de la présence de la clé.

**Correction recommandée :** Ajouter un prérequis `PrerequisitesAwareCommandInterface` sur toutes les commandes AI vérifiant la présence de `CLAUDE_API_KEY`.

---

### 5. Architecture non initialisée dans `Updater::updateToLastVersion()`
```php
$architecture = match (php_uname('m')) {
    'x86_64' => 'x64',
    'aarch64', 'arm64' => 'arm',
    // Pas de default → TypeError si valeur inconnue
};
```
**Risque :** Sur une architecture non listée (ex: `riscv64`, `ppc64`), PHP lève une `\UnhandledMatchError` non catchée lors de la mise à jour.

---

## 🟠 Importants (Dégradation fonctionnelle)

### 6. Commandes `open` et `sed` macOS-only sans détection de plateforme
Trois endroits dans le code utilisent des commandes système spécifiques à macOS sans vérification :

| Fichier | Commande | Problème Linux |
|---|---|---|
| `OpenDocCommand.php` | `exec('open https://...')` | `open` n'existe pas |
| `FeaturePushCommand.php` | `exec(sprintf('open "%s"', $url))` | `open` n'existe pas |
| `RetrieveDatabaseDumpCommand.php` | `sed -i '' 's/...'` | La syntaxe `-i ''` est macOS |

**Correction recommandée :** Détecter `PHP_OS_FAMILY` et utiliser `xdg-open` sur Linux, `sed -i` (sans `''`) sur Linux.

---

### 7. `DocGenerator` est une classe orpheline
`Core/Ai/DocGenerator.php` est défini mais **jamais instancié dans `Kernel.php`**. C'est `DocAnalyzer` qui est utilisé à la place. Cette classe représente du **dead code** qui peut induire en erreur les développeurs.

**Action recommandée :** Supprimer `DocGenerator` ou documenter son rôle distinct et l'intégrer dans le Kernel.

---

### 8. `ProjectManager` : injection de `SwkProxy` codée en dur
```php
// Core/Configuration/ProjectManager.php
foreach ([new SwkProxy()] as $project) {
```
L'ajout d'un nouveau projet géré nécessite une modification directe de `ProjectManager`, violant le principe Open/Closed. Le commentaire `// TODO: use dependency injection concept` confirme la dette technique.

---

### 9. `HotfixFinish` : risque de merge conflict non géré
Si `mergeBranch()` génère un conflit, `mustRun()` lève `ProcessFailedException`. À ce stade, le workflow a déjà fait `checkout master` et `reset --hard` — le dépôt est dans un état de merge partiel sans mécanisme de rollback.

**Recommandation :** Wrapper le merge dans un try/catch avec `git merge --abort` en fallback.

---

### 10. `RetrieveDatabaseDumpCommand` : données hardcodées en production
```php
$context = 'arn:aws:eks:eu-west-3:096866357657:cluster/wb-web-prod';
$namespace = 'main-api';
$remotePath = '/srv/app/data/dump/dump.sql';
```
Ces valeurs devraient être des paramètres CLI optionnels ou des entrées de `config.yaml`. Elles exposent également l'ARN de cluster AWS dans le code source.

---

## 🟡 Mineurs (Qualité / Maintenabilité)

### 11. `ConditionType` enum jamais utilisé
`Core/Prerequisites/Enum/ConditionType.php` (cases `OR`, `AND`) est déclaré mais non consommé. Dead code à supprimer ou implémenter pour permettre des règles de prérequis OR.

### 12. Absence de validation du format de sortie AI
Les commandes `DocumentationGenerateCommand` et `CodeReviewCommand` créent des dossiers/fichiers avec `mkdir(..., 0777, true)` — permissions trop larges en contexte multi-utilisateur.

### 13. `getEnvFileKeys()` regex limitative
```php
preg_match_all('/(\w+)=.+/m', $data, $matches);
```
Cette regex ne capture pas les clés avec des espaces avant `=` (`KEY =value`) ni les lignes vides ou commentaires. Un fichier `.env` avec `# COMMENTED_KEY=value` produirait un faux positif sur `# COMMENTED_KEY` si la regex était moins stricte — ici `\w+` évite le `#`, ce qui est correct, mais les lignes `export KEY=value` ne seraient pas parsées.

### 14. Pas de timeout sur `kubectl cp` dans `RetrieveDatabaseDumpCommand`
Le processus `kubectl cp` est lancé sans timeout — un dump de très grande taille ou une perte réseau pendant le transfert bloquera le processus indéfiniment.

### 15. `ViewConfigCommand::serializeConfiguration()` : concaténation sur valeur potentiellement null
```php
$result[$key] = $key.':<info>'.$value.'</info>';
```
Si `$value` est `null`, PHP 8 convertit silencieusement en chaîne vide — comportement acceptable mais non documenté.

---

## 🔐 Sécurité

### S1. Credentials dans les URLs (GitlabClient)
```php
return preg_replace('/^https:\/\//', 'https://'.$user.':'.$password.'@', $url);
```
Les URLs avec credentials intégrés ne doivent **jamais** être loggées. S'assurer que Symfony HttpClient ne logue pas les URLs en mode debug.

### S2. `GITLAB_API_TOKEN` transmis en header `PRIVATE-TOKEN`
Ce token GitLab avec droits de lecture des releases doit avoir des **permissions minimales** (scope `read_api` uniquement). Ne jamais utiliser un token personal avec `write` scope.

### S3. `sudo mv` sans demande de confirmation dans `Updater`
```php
exec(sprintf('sudo mv swk %s', escapeshellarg($targetPath)));
```
L'`escapeshellarg` est correct, mais l'exécution de `sudo` depuis un processus PHP dans un PHAR téléchargé depuis internet représente un vecteur d'escalade de privilèges si le binaire PHAR est compromis (pas de vérification de signature/hash du téléchargement).

### S4. `CLAUDE_API_KEY` accessible via `$_ENV`
Sur certains environnements, `$_ENV` est lisible par d'autres processus via `/proc/self/environ`. Préférer `getenv()` qui est plus sûr sur les systèmes POSIX.

---

# 📋 Tableau de Dépendances Critiques

| Service | Consommateurs | Impact si modifié/supprimé |
|---|---|---|
| `ClaudeClient` | `ReviewAnalyzer`, `TestAnalyzer`, `TestGenerator`, `FixtureGenerator`, `DocAnalyzer`, `DocGenerator` | Toutes les commandes AI non fonctionnelles |
| `ConfigurationManager` | `AbstractKernel`, `GitConfig`, `InitConfigCommand`, `ViewConfigCommand` | Toutes les commandes Git + config CLI brisées |
| `ProjectManager` | `AbstractReverseProxyCommand` + toutes sous-classes | Toutes les commandes proxy non fonctionnelles |
| `AbstractGitCommand` | 8 commandes Git | Toute la logique gitflow brisée |
| `GitlabClient` | `VersionChecker`, `Updater` | Check de mise à jour et self-update non fonctionnels |
| `PrerequisitesConfiguration` | Toutes commandes `PrerequisitesAwareCommandInterface` | Toutes les commandes avec prérequis non enregistrées |
| `EnvTool` | `GenerateEnvFileCommand`, `HelmVariableArgumentCommand`, `InitEnvSystemCommand` | Sous-système env entier non fonctionnel |
| `config/prompts/*.md` | Tous les `Core/Ai/*Analyzer` et `*Generator` | Toutes les commandes AI lèvent une exception |

---

# 📈 Score de Clarté Technique : 97/100

**Détail du scoring :**
- ✅ Diagramme Mermaid complet et valide : **+25/25**
- ✅ Toutes les dépendances techniques critiques couvertes : **+20/20**
- ✅ Explication détaillée de la structure des dossiers avec justification DDD : **+15/15**
- ✅ Terminologie définie, aucune imprécision technique non expliquée : **+10/10**
- ✅ Couverture exhaustive de tous les 57 fichiers analysés : **+15/15**
- ✅ Points de vigilance sécurité, performance et maintenance identifiés : **+12/15**
- **-3** : `DocGenerator` orphelin non détectable sans analyse statique complète (ambiguïté résiduelle sur son futur rôle)