# 📘 Documentation Fonctionnelle — sweeek CLI (`swk`)

## Vue d'ensemble

`swk` est l'outil en ligne de commande interne de sweeek. Il centralise et automatise les tâches quotidiennes des équipes de développement : gestion des branches Git, génération de fichiers de configuration, déploiement local, diagnostic, et assistance par intelligence artificielle. Son objectif est de fiabiliser les processus répétitifs et de réduire les erreurs humaines sur le cycle de vie des projets sweeek.

---

# 📋 Règles de Gestion

## 1. Démarrage & mise à jour automatique de l'outil

### 1.1 Vérification de mise à jour au lancement
- À chaque démarrage, l'outil vérifie automatiquement si une nouvelle version est disponible sur GitLab.
- Cette vérification n'est effectuée **qu'une fois par jour** (mise en cache pendant 24h) pour ne pas ralentir l'usage.
- Si une mise à jour est détectée, l'utilisateur est invité à confirmer l'installation.
  - **Si l'utilisateur accepte** : la mise à jour est téléchargée et installée automatiquement, puis l'outil s'arrête pour que la nouvelle version soit prise en compte.
  - **Si l'utilisateur refuse** : l'outil continue normalement jusqu'au prochain démarrage.
- La mise à jour automatique peut être désactivée ponctuellement avec le drapeau `--disable-update-checking` (ou `-d`).
- La mise à jour n'est disponible que pour les systèmes **Linux** et **macOS**, en architecture **x64** ou **ARM**.
- En cas d'échec de mise à jour, un message d'avertissement est affiché et l'outil continue de fonctionner.

### 1.2 Vérification manuelle (`cli:check-update`)
- L'utilisateur peut déclencher manuellement la vérification via `swk cli:check-update`.
- Affiche la version actuelle et, si disponible, la dernière version publiée.
- Propose une confirmation avant de lancer la mise à jour.
- En cas d'échec, affiche le message d'erreur et indique de réessayer plus tard.

### 1.3 Prérequis bloquants
- Chaque commande vérifie ses propres **prérequis** avant de s'exécuter.
- Si un prérequis n'est pas rempli, la commande est **masquée** et inaccessible (elle n'apparaît pas dans la liste des commandes disponibles).
- Les prérequis vérifiés peuvent porter sur : la présence d'un fichier ou dossier, l'installation d'un sous-projet, la plateforme ou l'architecture du poste, ou toute condition personnalisée.

---

## 2. Configuration de l'outil (`cli:config:*`)

### 2.1 Initialisation de la configuration (`cli:config:init`)
- Crée le fichier de configuration personnel de l'outil (`~/.swk/config.yaml`) **uniquement s'il n'existe pas**.
- Le fichier est généré avec les valeurs par défaut, commentées (non actives), pour guider l'utilisateur.
- **Condition d'échec** : si le fichier existe déjà, la commande est masquée (prérequis non satisfait).

### 2.2 Visualisation de la configuration (`cli:config:view`)
- Affiche le contenu de la configuration active sous forme arborescente dans le terminal.
- Configuration par défaut :
  - `git.main_remote` = `origin` (nom du dépôt principal)
  - `git.fork_remote` = `fork` (nom du dépôt forké)

---

## 3. Gestion des variables d'environnement (`env:*`)

Le système de gestion d'environnement repose sur un fichier central `env.config.yaml` qui recense toutes les variables de configuration par application.

### 3.1 Initialisation du système (`env:init`)
- Crée le fichier `env.config.yaml` dans le dossier courant avec une structure vide prête à être remplie.
- **Condition d'échec** : si le fichier existe déjà, la commande est bloquée.
- **Prérequis** : le dossier courant doit être un projet Git (présence du dossier `.git`).

### 3.2 Génération d'un fichier `.env` (`env:export:file`)
- **Entrées** : nom de l'application, chemin optionnel du fichier cible (par défaut `.{applicationName}.env`).
- Fusionne les sections `configmaps` et `secrets` de l'application depuis `env.config.yaml`.
- **Règle de déduplication** : si une clé est déjà présente dans le fichier `.env` cible, elle n'est **pas écrasée** (ajout uniquement des clés manquantes).
- Les noms de variables sont nettoyés : les préfixes de mapping sont retirés, le marqueur `{ENV}` est supprimé, et les underscores en début de nom sont enlevés.
- Le fichier `.env` est enrichi par ajout à la suite (non remplacé).
- **Condition d'échec** : si `env.config.yaml` est absent.

### 3.3 Génération des arguments Helm (`env:helm:arguments`)
- **Entrées** : nom de l'application, environnement cible (ex: `prod`, `staging`).
- Produit une chaîne d'arguments `--set` pour le déploiement Kubernetes/Helm.
- Pour chaque variable de type `secret` ou `configmap` : cherche d'abord la valeur dans les variables d'environnement du poste (scoped par environnement), puis utilise la valeur par défaut du fichier de config.
- Le scoping consiste à remplacer `{ENV}` dans le nom de la variable par la valeur de l'environnement fourni.
- **Condition d'échec** : si `env.config.yaml` est absent.

---

## 4. Gestion des branches Git (`feature:*`, `hotfix:*`, `demo:*`)

Toutes les commandes Git vérifient en prérequis que le dossier courant est bien un projet Git.

### 4.1 Nomenclature des branches
| Type | Format |
|---|---|
| Feature | `feature/{nom}` |
| Hotfix | `hotfix/{version}` (ex: `hotfix/1.4.3`) |
| Demo | `demo/demo` |

### 4.2 Démarrage d'une feature (`feature:start`)
- **Si des modifications locales non sauvegardées existent** : propose de les mettre de côté temporairement. Refus = échec de la commande. Option `--stash-changes` (`-s`) pour accepter automatiquement.
- Recherche la branche dans cet ordre de priorité :
  1. En local sur le poste
  2. Sur le dépôt forké
  3. Sur le dépôt principal
  4. Si inexistante : crée une nouvelle branche depuis `master` synchronisé avec le dépôt principal, effectue un commit d'initialisation vide, et pousse la branche.
- Restaure les modifications mises de côté à la fin de l'opération.

### 4.3 Publication d'une feature (`feature:push`)
- **Prérequis** : être positionné sur une branche de type `feature/`.
- **Si des modifications locales existent** : propose de les commiter. Refus = échec. Le message de commit est demandé interactivement (ne peut pas être vide).
- Pousse la branche sur le **dépôt forké** (pas le principal).
- Option `--force` (`-f`) disponible pour forcer la publication.
- Si GitLab renvoie un lien pour créer une Merge Request, propose d'ouvrir le navigateur avec l'URL pré-remplie.
- Demande le type de changement (nouvelle fonctionnalité, correction de bug, refactoring, documentation, autre) pour préfixer automatiquement le titre de la Merge Request avec un emoji.
- Le label `~RFR` (Request For Review) est ajouté automatiquement à la description.

### 4.4 Démarrage d'un hotfix (`hotfix:start`)
- Récupère la dernière version taguée sur `master` et incrémente le numéro de **correctif** (troisième chiffre : `1.4.2` → `1.4.3`).
- **Si des modifications locales existent** : même comportement que `feature:start`.
- Synchronise `master` avec le dépôt principal avant toute opération.
- Si la branche hotfix existe déjà en distant : la récupère (après suppression de l'éventuelle version locale).
- Si elle n'existe pas : la crée, effectue un commit d'initialisation vide, et pousse.

### 4.5 Fusion d'une feature dans un hotfix (`hotfix:merge`)
- **Prérequis** : pas de modifications locales non sauvegardées.
- Lance automatiquement `hotfix:start` pour se positionner sur la bonne branche de correctif.
- Recherche la feature d'abord en local, puis en distant (dépôt principal).
- **Condition d'échec** : si la feature est introuvable partout.
- Pousse le hotfix après fusion.

### 4.6 Clôture d'un hotfix (`hotfix:finish`)
- **Prérequis** : pas de modifications locales non sauvegardées.
- Vérifie que l'on est bien positionné sur la bonne branche hotfix. Sinon, propose de lancer `hotfix:start`.
- **Si la branche hotfix est vide** (seulement le commit d'initialisation) : propose d'annuler le hotfix via `hotfix:abort`.
- Si la branche est valide :
  1. Retourne sur `master` et le synchronise.
  2. Fusionne la branche hotfix dans `master`.
  3. Crée un nouveau tag de version.
  4. Pousse `master` et le tag vers le dépôt principal.
  5. Supprime la branche hotfix en local et en distant.

### 4.7 Annulation d'un hotfix (`hotfix:abort`)
- Calcule la branche hotfix attendue (même logique que `hotfix:start`).
- Demande une confirmation explicite avant toute suppression.
- Supprime la branche hotfix **en local ET en distant** (dépôt principal).
- Retourne sur `master`.

### 4.8 Démarrage d'une branche de démo (`demo:start`)
- **Prérequis** : pas de modifications locales non sauvegardées.
- Synchronise `master` avec le dépôt principal.
- Si la branche `demo/demo` existe en local : la supprime.
- Si `demo/demo` n'existe pas en distant : la crée et pousse.
- Si `demo/demo` existe en distant : la récupère et vérifie que la base de code est synchronisée avec la dernière version de production (dernier tag). Si ce n'est pas le cas, affiche un avertissement avec la commande Git à exécuter manuellement.

### 4.9 Fusion d'une feature dans la démo (`demo:merge-feature`)
- **Si des modifications locales existent** : propose de les mettre de côté. Refus = échec.
- Lance automatiquement `demo:start` pour se positionner sur la bonne branche.
- Recherche la feature d'abord en local, puis en distant (dépôt principal).
- **Condition d'échec** : si la feature est introuvable.
- Pousse la démo après fusion, puis restaure les modifications mises de côté si applicable.

### 4.10 Gestion des versions
- Le format de version attendu est `X.Y.Z` (ex: `1.4.2`).
- Incrémentation :
  - `X` : version majeure
  - `Y` : version fonctionnelle
  - `Z` : correctif (hotfix)
- Si aucun tag valide n'est trouvé sur la branche, le système part de `0.0.0`.
- Tout tag ne respectant pas ce format est ignoré.

---

## 5. Proxy de développement local (`proxy:*`)

Le proxy local (`swk-proxy`) permet aux développeurs de router le trafic réseau vers leurs environnements locaux.

### 5.1 Installation (`proxy:install`)
- **Prérequis** : le proxy ne doit pas être déjà installé ET l'ancien binaire `/usr/local/bin/swk-proxy` ne doit pas exister.
- Clone le dépôt Git du proxy dans `~/.swk/projects/swk-proxy`.
- Lance la procédure d'installation du proxy.

### 5.2 Migration depuis l'ancienne version (`proxy:migrate`)
- **Prérequis** : le proxy n'est pas encore installé via le nouveau système ET l'ancien binaire `/usr/local/bin/swk-proxy` existe.
- Arrête l'ancienne version du proxy.
- Déplace les fichiers de l'ancienne installation vers le nouvel emplacement.
- Supprime l'ancien binaire.

### 5.3 Démarrage (`proxy:start`) / Démarrage avec tunnel public (`proxy:start:ngrok`)
- **Prérequis** : le proxy doit être installé.
- `proxy:start` : démarre l'environnement proxy local.
- `proxy:start:ngrok` : démarre l'environnement avec activation du tunnel ngrok (accès depuis l'extérieur du réseau local).

### 5.4 Arrêt (`proxy:stop`)
- Arrête l'environnement proxy local.

### 5.5 Mise à jour (`proxy:update`)
- Met à jour le projet proxy vers sa dernière version.

### 5.6 Désinstallation (`proxy:uninstall`)
- Désinstalle complètement le proxy local.

### 5.7 Diagnostic (`proxy:doctor`)
- Lance un diagnostic de l'état du proxy et affiche les résultats dans le terminal.

---

## 6. Récupération d'une sauvegarde de base de données (`project:database:retrieve-dump`)

- Se connecte au cluster Kubernetes de production sweeek (AWS EKS, région `eu-west-3`).
- Identifie automatiquement le pod d'administration (`sweeek-api-master-admin-*`).
- **Condition d'échec** : si aucun pod d'administration n'est trouvé.
- Affiche la taille du fichier à télécharger (en Mo) et une barre de progression pendant le transfert.
- Télécharge le fichier `dump.sql` depuis le pod vers le dossier `~/Downloads` par défaut (modifiable via argument).
- Après téléchargement, nettoie automatiquement les caractères d'échappement parasites dans le fichier SQL.
- **Condition d'échec** : si le téléchargement échoue ou si kubectl n'est pas disponible.

---

## 7. Assistance par intelligence artificielle (`ai:*`)

Les fonctionnalités IA utilisent le modèle Claude (Anthropic) et nécessitent une clé API valide (`CLAUDE_API_KEY`). L'IA effectue jusqu'à 2 tentatives automatiques en cas d'échec de communication.

### 7.1 Revue de code automatisée (`ai:review`)
- **Modes d'analyse** :
  - **Par différence Git** : compare deux branches ou commits (défaut : changements locaux non commitée sur `HEAD`).
  - **Par fichier entier** : analyse un fichier spécifique avec l