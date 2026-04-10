# 📘 Documentation Fonctionnelle — SWK CLI (sweeek)

## Résumé de la valeur métier

**SWK CLI** est l'outil en ligne de commande interne de sweeek. Il centralise et automatise l'ensemble des tâches répétitives du quotidien des développeurs : gestion des branches Git, gestion des variables d'environnement pour les déploiements, contrôle du proxy local, et assistance par intelligence artificielle (revue de code, génération de tests, génération de documentation). L'objectif est de fiabiliser les processus, réduire les erreurs humaines et accélérer les cycles de livraison.

---

# 📋 Règles de Gestion

## 1. Démarrage & Mise à jour automatique

| Règle | Détail |
|---|---|
| **Vérification de version au lancement** | À chaque démarrage de `swk`, l'outil vérifie s'il existe une version plus récente. Cette vérification est mise en cache **24h** pour ne pas ralentir l'usage quotidien. |
| **Désactivation possible** | L'utilisateur peut désactiver la vérification de mise à jour avec l'option `--disable-update-checking` (ou `-d`). |
| **Proposition interactive** | Si une mise à jour est disponible, l'utilisateur est invité à accepter ou refuser. En cas d'acceptation, la mise à jour s'applique automatiquement. |
| **Mise à jour selon la plateforme** | La mise à jour télécharge le bon binaire selon le système d'exploitation (Linux ou macOS) et l'architecture matérielle (x64 ou ARM). |
| **Plateformes non supportées** | Si le système ne correspond ni à Linux ni à macOS, la mise à jour est bloquée avec un message d'erreur. |
| **Après mise à jour réussie** | L'outil se ferme immédiatement pour que l'utilisateur relance la nouvelle version. |

---

## 2. Prérequis des commandes

Chaque commande peut déclarer des **conditions obligatoires** pour s'exécuter. Si une condition n'est pas remplie, la commande est **invisible et inaccessible** (elle n'apparaît même pas dans la liste des commandes disponibles).

| Type de prérequis | Exemple d'usage |
|---|---|
| **Fichier ou dossier requis** | La commande `env:helm:arguments` n'est disponible que si le fichier `env.config.yaml` existe dans le projet. |
| **Fichier ou dossier absent requis** | La commande `env:init` n'est disponible que si `env.config.yaml` n'existe PAS encore. |
| **Dépôt Git requis** | Toutes les commandes Git vérifient qu'on est bien dans un dossier géré par Git. |
| **Projet installé requis** | Les commandes du proxy ne sont disponibles que si le projet `swk-proxy` est installé sur le poste. |
| **Système d'exploitation** | Certaines commandes peuvent être restreintes à Linux ou macOS. |
| **Architecture matérielle** | Certaines commandes peuvent être restreintes à x64, x86, ARM32 ou ARM64. |

---

## 3. Gestion de la configuration SWK

### `cli:config:init` — Initialisation de la configuration
- Crée le fichier de configuration `~/.swk/config.yaml` s'il n'existe pas.
- Le fichier est créé avec des valeurs par défaut, toutes **commentées** (inactives), pour guider l'utilisateur.
- **Bloqué si** le fichier existe déjà (prérequis).

### `cli:config:view` — Consultation de la configuration
- Affiche l'arborescence complète de la configuration active dans le terminal.
- Toujours disponible, même si la config n'est pas personnalisée (les valeurs par défaut s'appliquent alors).

### Valeurs par défaut de la configuration Git
| Paramètre | Valeur par défaut |
|---|---|
| Nom du dépôt principal (`main_remote`) | `origin` |
| Nom du dépôt fork (`fork_remote`) | `fork` |

---

## 4. Gestion des variables d'environnement

### `env:init` — Initialisation
- Crée un fichier `env.config.yaml` dans le dossier du projet en cours.
- **Bloqué si** le fichier existe déjà.
- **Bloqué si** le dossier n'est pas un projet Git (`.git` absent).
- Crée la structure vide : une section `_mapping` (correspondances de noms), et une section par application avec `secrets` et `configmaps`.

### `env:export:file` — Génération d'un fichier `.env`
- Génère un fichier `.env` pour une application donnée à partir du fichier `env.config.yaml`.
- Si aucun nom de fichier n'est précisé, le fichier généré s'appelle `.<nom_application>.env`.
- **Règle d'unicité** : les variables déjà présentes dans le fichier `.env` cible ne sont **pas écrasées ni dupliquées**.
- Les noms de variables sont **nettoyés** : suppression des préfixes de mapping, du placeholder `{ENV}`, et du tiret bas initial.
- **Bloqué si** `env.config.yaml` n'existe pas.

### `env:helm:arguments` — Génération des arguments de déploiement
- Génère une chaîne d'arguments au format Helm (`--set app.secrets.NOM=valeur`) pour un environnement donné (ex : `prod`, `staging`).
- Les valeurs sont résolues dans l'ordre suivant : variable d'environnement système → valeur par défaut dans le fichier de config.
- Distingue deux catégories de variables : les **secrets** (données sensibles) et les **configmaps** (configuration non sensible).
- **Bloqué si** `env.config.yaml` n'existe pas.

### Règles de nommage des variables
| Opération | Règle |
|---|---|
| Nom propre (`getCleanVariableName`) | Supprime les clés de mapping, remplace `{ENV}` par rien, supprime le `_` initial |
| Nom scopé (`getScopedVariableName`) | Remplace les clés de mapping par leurs valeurs, remplace `{ENV}` par le nom de l'environnement |

---

## 5. Gestion des branches Git

> Toutes les commandes Git vérifient systématiquement la présence d'un dépôt Git valide avant de s'exécuter.

### Gestion des Hotfix (corrections urgentes en production)

#### `hotfix:start` — Démarrer un correctif urgent
1. Si des modifications locales non sauvegardées existent : l'utilisateur est invité à les mettre de côté (stash). Refus = arrêt.
2. Calcule automatiquement le **numéro de version du hotfix** en incrémentant le numéro de patch de la dernière version taguée (ex : `1.2.3` → `1.2.4`).
3. Si la branche hotfix existe déjà sur le dépôt distant : bascule dessus directement.
4. Sinon : crée la branche, crée un commit d'initialisation vide (marqué `[skip ci]` pour ne pas déclencher la CI), et pousse sur le dépôt principal.
5. Ré-applique les modifications mises de côté si applicable.

#### `hotfix:merge` — Intégrer une fonctionnalité dans un hotfix
1. **Bloqué si** des modifications locales non sauvegardées existent.
2. Démarre ou bascule sur la branche hotfix (appel interne à `hotfix:start`).
3. Cherche la branche feature spécifiée : d'abord en local, puis sur le dépôt distant.
4. **Bloqué si** la branche feature est introuvable.
5. Fusionne avec un commit de merge explicite (pas de fast-forward).

#### `hotfix:finish` — Clôturer et livrer un hotfix
1. **Bloqué si** des modifications locales existent.
2. Vérifie qu'on est bien sur la bonne branche hotfix.
   - Si on est sur une branche hotfix incorrecte/obsolète : propose de relancer `hotfix:start`.
   - Si on n'est pas sur une branche hotfix du tout : bloque.
3. **Détection d'un hotfix vide** : si le seul commit est le commit d'initialisation `[swk] Init hotfix`, le hotfix est considéré comme vide. L'utilisateur est invité à l'annuler.
4. Si le hotfix contient des commits :
   - Bascule sur `master`, récupère les dernières modifications du dépôt principal.
   - Fusionne la branche hotfix dans `master`.
   - Crée un **tag de version** avec le nouveau numéro.
   - Pousse `master` et le tag sur le dépôt principal.
   - Supprime la branche hotfix en local et sur le dépôt distant.

#### `hotfix:abort` — Annuler un hotfix
1. Demande confirmation explicite à l'utilisateur (réponse par défaut : **Non**).
2. En cas de confirmation : bascule sur `master`, supprime la branche hotfix en local et sur le dépôt distant.

---

### Gestion des Features (nouvelles fonctionnalités)

#### `feature:start` — Démarrer une fonctionnalité
1. Gestion des modifications locales : stash proposé ou forcé via l'option `--stash-changes`.
2. Recherche de la branche `feature/<nom>` dans l'ordre :
   - En local → bascule dessus.
   - Sur le fork distant → bascule dessus.
   - Sur le dépôt principal distant → bascule dessus.
   - Introuvable → crée la branche depuis `master` à jour, commit d'init `[skip ci]`, pousse.
3. Ré-applique les modifications mises de côté si applicable.

#### `feature:push` — Pousser une fonctionnalité
1. **Bloqué si** on n'est pas sur une branche `feature/`.
2. Si des modifications locales existent : propose de les committer (le message de commit est saisi interactivement, ne peut pas être vide).
3. Pousse vers le **fork distant** (pas le dépôt principal).
4. Si GitLab retourne une URL de création de Merge Request : propose d'ouvrir le navigateur avec l'URL pré-remplie.
5. Si Merge Request : demande le type de changement pour choisir l'emoji de préfixe du titre.

| Type de changement | Préfixe |
|---|---|
| Nouvelle fonctionnalité | 🚧 |
| Correction de bug | 🐛 |
| Refactoring | ♻️ |
| Documentation | 📝 |
| Autre | *(aucun)* |

---

### Gestion de l'environnement Demo

#### `demo:start` — Préparer l'environnement de démonstration
1. **Bloqué si** des modifications locales existent.
2. Met à jour `master` depuis le dépôt principal.
3. Récupère le dernier tag de production.
4. Si la branche `demo/demo` existe en local : la supprime avant de continuer.
5. Si la branche `demo/demo` n'existe pas sur le dépôt distant : la crée et la pousse.
6. Si elle existe : bascule dessus et vérifie la synchronisation avec la production. Si la demo est en retard sur la production : affiche un avertissement avec la commande à exécuter manuellement.

#### `demo:merge-feature` — Intégrer une fonctionnalité dans la demo
1. Si des modifications locales existent : propose de les mettre de côté (stash).
2. Bascule sur la branche demo (appel interne à `demo:start`).
3. Recherche la branche feature en local puis sur le dépôt principal.
4. **Bloqué si** la branche feature est introuvable.
5. Fusionne et pousse sur le dépôt distant.
6. Ré-applique les modifications mises de côté si applicable.

---

### Versionnement (tags)
- Format obligatoire : **`X.Y.Z`** (trois nombres séparés par des points).
- Tout tag ne respectant pas ce format est ignoré.
- En l'absence de tag valide, la version de référence est `0.0.0`.
- Trois types d'incrémentation :
  - **Major** (X) : changement majeur
  - **Feature** (Y) : nouvelle fonctionnalité
  - **Minor/Patch** (Z) : correction urgente (hotfix)

---

## 6. Gestion du Reverse Proxy (`swk-proxy`)

| Commande | Condition d'accès | Action |
|---|---|---|
| `proxy:install` | `swk-proxy` **non** installé ET pas d'ancienne installation | Clone le dépôt et installe |
| `proxy:start` | `swk-proxy` installé | Démarre l'environnement proxy |
| `proxy:stop` | `swk-proxy` installé | Arrête l'environnement proxy |
| `proxy:start:ngrok` | `swk-proxy` installé | Démarre avec exposition publique via ngrok |
| `proxy:update` | `swk-proxy` installé | Met à jour le proxy |
| `proxy:uninstall` | `swk-proxy` installé | Désinstalle le proxy |
| `proxy:doctor` | `swk-proxy` installé | Lance un diagnostic de l'environnement |
| `proxy:migrate` | `swk-proxy` **non** installé via swk ET ancienne installation détectée | Migre l'ancienne installation vers la nouvelle structure |

**Règle de migration** : La migration (`proxy:migrate`) est uniquement disponible si une ancienne version de `swk-proxy` est installée à l'ancien emplacement (`/usr/local/bin/swk-proxy`) ET que la nouvelle installation n'est pas encore en place.

---

## 7. Récupération de la base de données de production

### `project:database:retrieve-dump`
1. Se connecte au cluster Kubernetes de production AWS (`wb-web-prod`, région `eu-west-3`).
2. Identifie automatiquement le pod d'administration actif (`sweeek-api-master-admin-*`).
3. **Bloqué si** aucun pod admin n'est trouvé.
4. Récupère la taille du dump pour afficher une barre de progression en mégaoctets.
5. Télécharge le fichier `dump.sql` vers le dossier de destination (par défaut : `~/Downloads`).
6. Post-traitement automatique : supprime les backslashes parasites dans le fichier SQL. Un avertissement est affiché si cette étape échoue (non bloquant).

---

## 8. Assistance par Intelligence Artificielle

> Les fonctionnalités IA nécessitent une clé API Claude (Anthropic) configurée via la variable d'environnement `CLAUDE_API_KEY`. L'IA effectue automatiquement **2 tentatives** en cas d'échec de connexion.

### `ai:review` — Revue de code automatisée

| Règle | Détail |
|---|---|
| **Source d'analyse** | Soit un fichier entier (`--file`), soit les différences Git entre deux branches/commits |
| **Analyse d'un fichier** | Le fichier d