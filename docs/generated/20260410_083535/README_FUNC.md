# 📘 Documentation Fonctionnelle — sweeek CLI (`swk`)

## Résumé de la valeur métier

**sweeek CLI** (commande `swk`) est l'outil en ligne de commande interne de sweeek. Il centralise et automatise les tâches répétitives du quotidien des équipes de développement : gestion des branches Git, configuration des environnements, génération de documentation et de tests par IA, surveillance du proxy local, et récupération de sauvegardes de base de données. L'objectif est de réduire les erreurs humaines, d'accélérer les flux de travail et de garantir des pratiques cohérentes au sein de l'équipe.

---

# 📋 Règles de Gestion

## 1. Mise à jour automatique de l'outil (`cli:check-update`)

| Règle | Détail |
|---|---|
| **Vérification quotidienne** | L'outil vérifie automatiquement l'existence d'une nouvelle version une fois par jour maximum. |
| **Option de désactivation** | La vérification peut être désactivée ponctuellement via l'option `--disable-update-checking` (`-d`). |
| **Confirmation utilisateur** | Si une mise à jour est disponible, l'utilisateur est invité à confirmer avant toute installation. |
| **Compatibilité système** | La mise à jour est adaptée au système d'exploitation (Linux ou macOS) et à l'architecture processeur (x64 ou ARM). |
| **Échec géré** | En cas d'échec de mise à jour, un message d'erreur est affiché et l'outil continue de fonctionner. |
| **Redémarrage automatique** | Après une mise à jour réussie, l'outil s'arrête pour que la nouvelle version soit prise en compte à la prochaine utilisation. |
| **Commande manuelle** | La commande `cli:check-update` permet également de déclencher la vérification et la mise à jour manuellement. |

---

## 2. Configuration de l'outil (`cli:config:init` / `cli:config:view`)

| Règle | Détail |
|---|---|
| **Initialisation unique** | La commande `cli:config:init` ne s'exécute que si le fichier de configuration n'existe pas encore. Elle est bloquée si le fichier est déjà présent. |
| **Fichier de configuration commenté** | Le fichier généré contient la configuration par défaut entièrement commentée, pour guider l'utilisateur. |
| **Valeurs par défaut Git** | Le dépôt principal est nommé `origin` et le dépôt de travail personnel est nommé `fork` par défaut. |
| **Visualisation arborescente** | La commande `cli:config:view` affiche la configuration active sous forme d'arbre lisible dans le terminal. |

---

## 3. Gestion des variables d'environnement

### Initialisation (`env:init`)
| Règle | Détail |
|---|---|
| **Prérequis Git** | La commande ne peut s'exécuter que dans un dossier versionné avec Git (présence d'un dossier `.git`). |
| **Création unique** | Si le fichier `env.config.yaml` existe déjà, la commande échoue avec un message d'erreur. |
| **Structure par défaut** | Le fichier créé contient une section `_mapping` (vide) et une section `app` avec `secrets` et `configmaps` (vides). |

### Génération du fichier `.env` (`env:export:file`)
| Règle | Détail |
|---|---|
| **Prérequis fichier de config** | La commande échoue si `env.config.yaml` est absent. |
| **Nom de fichier automatique** | Si aucun nom de fichier de destination n'est précisé, le fichier généré est nommé `.{nomApplication}.env`. |
| **Pas de doublons** | Les variables déjà présentes dans le fichier `.env` cible ne sont pas réécrites. |
| **Fusion secrets + configmaps** | Les variables de configuration et les secrets sont fusionnés dans le fichier de sortie. |
| **Ajout en fin de fichier** | Les nouvelles variables sont ajoutées à la suite du fichier existant, sans écraser le contenu précédent. |

### Génération des arguments Helm (`env:helm:arguments`)
| Règle | Détail |
|---|---|
| **Prérequis fichier de config** | La commande échoue si `env.config.yaml` est absent. |
| **Résolution par environnement** | Les variables sont résolues en tenant compte de l'environnement cible fourni (ex : `production`, `staging`). |
| **Priorité aux variables système** | Si une variable est définie dans l'environnement système de la machine, elle prend la priorité sur la valeur du fichier de configuration. |
| **Nettoyage des noms** | Les noms de variables sont nettoyés (suppression des préfixes d'environnement, des tirets bas superflus) avant d'être utilisés comme arguments Helm. |
| **Sortie sur une ligne** | Tous les arguments générés sont affichés sur une seule ligne, prêts à être copiés-collés. |

---

## 4. Flux de travail Git

> **Règle transversale :** Toutes les commandes Git vérifient que l'outil est bien exécuté dans un dossier Git. En cas d'erreur Git bloquante, un message d'erreur détaillé est affiché et la commande s'arrête proprement.

### Démarrer une fonctionnalité (`feature:start`)
| Règle | Détail |
|---|---|
| **Modifications locales** | Si des fichiers sont modifiés localement, l'utilisateur est invité à les mettre de côté avant de continuer (ou peut utiliser l'option `-s`). |
| **Priorité de recherche** | L'outil cherche la branche dans cet ordre : 1) en local, 2) sur le dépôt personnel (fork), 3) sur le dépôt principal. |
| **Création depuis master** | Si la branche n'existe nulle part, elle est créée à partir de la version la plus récente du dépôt principal. |
| **Convention de nommage** | La branche est automatiquement préfixée `feature/`. |
| **Commit d'initialisation** | Un commit vide `[swk] Init feature ...` est créé et publié pour matérialiser le démarrage. |
| **Restauration des modifications** | Si des fichiers avaient été mis de côté, ils sont restaurés automatiquement en fin de commande. |

### Pousser une fonctionnalité (`feature:push`)
| Règle | Détail |
|---|---|
| **Contrôle de branche** | La commande échoue si on n'est pas sur une branche `feature/`. |
| **Commit interactif** | Si des modifications locales sont détectées, l'utilisateur est invité à saisir un message de commit. Le message ne peut pas être vide. |
| **Publication sur le fork** | Le code est publié sur le dépôt personnel (fork), pas sur le dépôt principal. |
| **Création de merge request** | Si GitLab détecte qu'un lien de création de merge request est disponible, l'outil propose d'ouvrir le navigateur. |
| **Étiquette automatique** | Selon le type de fonctionnalité choisi (nouvelle feature, bug, refactoring, documentation), un emoji est automatiquement ajouté au titre de la merge request. |
| **Label RFR automatique** | La description de la merge request est pré-remplie avec `/labels ~RFR`. |
| **Force push disponible** | L'option `-f` permet de forcer la publication si nécessaire. |

### Démarrer un correctif urgent (`hotfix:start`)
| Règle | Détail |
|---|---|
| **Numéro de version automatique** | Le nom de la branche est calculé automatiquement en incrémentant le dernier numéro de version de production. |
| **Modifications locales** | Même comportement que `feature:start` : mise de côté proposée ou forcée via `-s`. |
| **Réutilisation si existante** | Si la branche de correctif existe déjà à distance, elle est récupérée et utilisée directement. |
| **Convention de nommage** | La branche est automatiquement préfixée `hotfix/`. |
| **Commit d'initialisation** | Un commit vide `[swk] Init hotfix ...` est créé pour matérialiser le démarrage. |

### Intégrer une fonctionnalité dans un correctif (`hotfix:merge`)
| Règle | Détail |
|---|---|
| **Pas de modifications locales** | Des fichiers modifiés localement bloquent la commande. |
| **Démarrage automatique du correctif** | La commande `hotfix:start` est appelée automatiquement pour se positionner sur la bonne branche. |
| **Priorité local vs distant** | La branche de fonctionnalité est cherchée d'abord en local, puis à distance. |
| **Blocage si branche absente** | Si la branche de fonctionnalité n'est pas trouvée, la commande échoue. |

### Finaliser un correctif (`hotfix:finish`)
| Règle | Détail |
|---|---|
| **Pas de modifications locales** | Des fichiers modifiés localement bloquent la commande. |
| **Contrôle de validité** | L'outil vérifie que l'on est bien sur la bonne branche de correctif. Si ce n'est pas le cas, il propose de lancer `hotfix:start`. |
| **Correctif vide refusé** | Si aucun vrai commit n'a été ajouté (le dernier commit est celui d'initialisation), l'outil propose d'annuler le correctif. |
| **Fusion dans master** | Le correctif est fusionné dans la branche principale avec un message de commit explicite. |
| **Création d'un tag de version** | Un nouveau tag de version est créé et publié automatiquement. |
| **Nettoyage des branches** | La branche de correctif est supprimée en local et à distance après la fusion. |

### Annuler un correctif (`hotfix:abort`)
| Règle | Détail |
|---|---|
| **Confirmation obligatoire** | L'utilisateur doit confirmer explicitement l'annulation (la réponse par défaut est **Non**). |
| **Suppression complète** | La branche est supprimée à la fois en local et à distance. |

### Environnement de démonstration (`demo:start` / `demo:merge-feature`)
| Règle | Détail |
|---|---|
| **Pas de modifications locales pour `demo:start`** | Des fichiers modifiés localement bloquent le démarrage. |
| **Création si absente** | Si la branche `demo/demo` n'existe pas à distance, elle est créée et publiée. |
| **Avertissement de désynchronisation** | Si la version de la démo est en retard par rapport à la production, un avertissement est affiché avec la commande à exécuter manuellement. |
| **Mise de côté pour `demo:merge-feature`** | Des modifications locales peuvent être mises de côté avec confirmation de l'utilisateur. |
| **Recherche local puis distant** | La branche de fonctionnalité est cherchée d'abord en local, puis à distance. |
| **Restauration des modifications** | Les fichiers mis de côté sont restaurés après la fusion. |

---

## 5. Proxy local (`proxy:*`)

| Règle | Détail |
|---|---|
| **Installation préalable obligatoire** | Les commandes `start`, `stop`, `doctor`, `update`, `uninstall`, `start:ngrok` exigent que le projet `swk-proxy` soit installé. |
| **Installation bloquée si déjà présent** | `proxy:install` est bloquée si `swk-proxy` est déjà installé ou si l'ancienne version (`/usr/local/bin/swk-proxy`) est détectée. |
| **Migration depuis l'ancienne version** | `proxy:migrate` est disponible uniquement si l'ancienne version est présente et que la nouvelle n'est **pas** déjà installée. Elle arrête l'ancienne version, déplace les fichiers et supprime l'ancien exécutable. |
| **Démarrage avec tunnel externe** | `proxy:start:ngrok` démarre le proxy avec une URL publique temporaire (ngrok), utile pour les tests avec des services externes. |
| **Diagnostic** | `proxy:doctor` lance des vérifications automatiques de bon fonctionnement du proxy. |

---

## 6. Récupération de la base de données de production (`project:database:retrieve-dump`)

| Règle | Détail |
|---|---|
| **Environnement cible fixe** | La commande cible exclusivement le cluster de production AWS (`arn:aws:eks:eu-west-3:096866357657:cluster/wb-web-prod`) et le namespace `main-api`. |
| **Identification automatique du pod** | L'outil identifie automatiquement le pod d'administration actif (pattern `sweeek-api-master-admin-*`). |
| **Erreur si pod absent** | Si aucun pod correspondant n'est trouvé, la commande s'arrête avec un message d'erreur. |
| **Dossier de destination par défaut** | Sans argument, le fichier est sauvegardé dans le dossier `Téléchargements` (`~/Downloads`). |
| **Barre de progression** | La progression du téléchargement est affichée en mégaoctets. |
| **Nettoyage automatique du fichier** | Après téléchargement, les caractères parasites (`\-`) sont automatiquement supprimés du fichier. |
| **Avertissement si nettoyage impossible** | Si le nettoyage échoue, un simple avertissement est affiché sans bloquer la commande. |

---

## 7. Fonctionnalités IA (`ai:*`)

### Revue de code automatique (`ai:review`)
| Règle | Détail |
|---|---|
| **Deux modes d'analyse** | L'IA peut analyser soit les modifications Git (entre deux commits ou branches), soit un fichier entier via l'option `--file`. |
| **Fichier vide ignoré** | Si le fichier fourni est vide, la commande se termine sans erreur. |
| **Aucun changement Git ignoré** | Si aucune modification Git n'est détectée, la commande se termine sans erreur. |
| **Contexte personnalisable** | Un contexte projet peut être précisé pour affiner l'analyse. |
| **Export optionnel** | L'option `--export` sauvegarde le rapport dans `reports/AI_REVIEW.md`. Le dossier est créé automatiquement si absent. |
| **Seuil de rejet automatique** | Si le rapport contient le mot `REJECTED`, la commande se termine en échec avec une alerte qualité, indiquant un score inférieur à 80/100. |
| **Statut de succès explicite** | En l'absence de rejet, un message de validation est affiché. |

### Génération de documentation (`ai:doc:generate`)
| Règle | Détail |
|---|---|
| **Double documentation générée** | L'IA produit simultanément une documentation **technique** et une documentation **fonctionnelle**. |
| **Formats supportés** | Le format de sortie peut être `md` (Markdown)