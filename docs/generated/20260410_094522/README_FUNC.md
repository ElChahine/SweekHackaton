# 📘 Documentation Fonctionnelle

## Module de Mise à Jour Automatique de l'outil CLI sweeek (`swk`)

Ce module gère la **mise à jour automatique de l'outil en ligne de commande sweeek** (`swk`), utilisé en interne. Il permet de :

1. **Connaître la version actuellement installée** sur la machine de l'utilisateur
2. **Vérifier si une version plus récente est disponible** sur le dépôt officiel
3. **Télécharger et installer automatiquement la dernière version**, en s'adaptant au type de machine utilisée

---

# 📋 Règles de Gestion

## 🔵 Gestion des versions

| # | Règle | Comportement |
|---|-------|-------------|
| RG-01 | La version installée est lue depuis un fichier interne à l'outil (`.app.version`) | Si ce fichier est absent ou illisible, la version retournée est `UNKNOWN` |
| RG-02 | La dernière version disponible est récupérée depuis le dépôt officiel GitLab sweeek | En cas d'échec de connexion ou d'indisponibilité du dépôt, la version actuelle est renvoyée par défaut (pas d'erreur bloquante) |
| RG-03 | Une mise à jour est considérée **disponible** uniquement si la version distante est **différente** de la version locale | Toute différence (même rétrogradation) déclenche le signal de mise à jour |

> ⚠️ **Attention métier (RG-03)** : Le système ne vérifie pas si la version distante est *plus récente*, seulement si elle est *différente*. Une régression de version serait donc signalée comme "mise à jour disponible".

---

## 🔵 Processus de mise à jour

| # | Règle | Comportement en cas d'échec |
|---|-------|----------------------------|
| RG-04 | La mise à jour est adaptée automatiquement au **système d'exploitation** de la machine | Seuls **Linux** et **macOS** sont supportés. Sur tout autre système (ex: Windows), la mise à jour est bloquée avec un message d'erreur explicite |
| RG-05 | La mise à jour est adaptée automatiquement à l'**architecture matérielle** de la machine | Seules les architectures **x64** (Intel/AMD classique) et **ARM** (Apple Silicon, Raspberry Pi…) sont supportées. Toute autre architecture bloque la mise à jour |
| RG-06 | L'archive de la nouvelle version est **téléchargée depuis GitLab** via l'URL correspondant à la plateforme et l'architecture détectées | Si l'URL est introuvable ou inaccessible, la mise à jour échoue |
| RG-07 | Après téléchargement, l'archive est **décompressée** automatiquement sur la machine | Si la décompression échoue (archive corrompue, droits insuffisants…), le processus s'arrête |
| RG-08 | L'archive temporaire de téléchargement est **supprimée** après décompression | Nettoyage automatique, sans action manuelle requise |
| RG-09 | Le nouvel exécutable `swk` est rendu **exécutable** puis **déplacé à l'emplacement de l'ancienne version** | Cette opération nécessite les **droits administrateur** (`sudo`) sur la machine. Sans ces droits, la mise à jour échouera à l'étape finale |

---

# 🚀 Cas d'Usage & Scénarios

### Scénario 1 — Vérification silencieuse au démarrage de l'outil
> Un développeur ou un opérateur lance `swk`. L'outil vérifie en arrière-plan si une nouvelle version existe.
> - ✅ Si la version est à jour → aucun message perturbant
> - 🔔 Si une version plus récente est disponible → un message invite l'utilisateur à mettre à jour

---

### Scénario 2 — Mise à jour sur un Mac Apple Silicon (ARM)
> Un utilisateur sur MacBook M2 lance la commande de mise à jour.
> - L'outil détecte : système = `macOS`, architecture = `ARM`
> - Il télécharge le bon binaire depuis GitLab (`mac` + `arm`)
> - Il remplace automatiquement l'ancienne version
> - ✅ L'outil est mis à jour sans intervention manuelle

---

### Scénario 3 — Tentative de mise à jour sur un poste Windows
> Un utilisateur tente de mettre à jour `swk` depuis un environnement Windows.
> - ❌ L'outil détecte un système non supporté et bloque la mise à jour avec un message d'erreur clair
> - Aucune modification n'est effectuée sur la machine

---

### Scénario 4 — GitLab inaccessible lors de la vérification de version
> Le dépôt GitLab est temporairement indisponible.
> - ✅ L'outil ne plante pas : il considère que la version actuelle est la dernière connue
> - Aucune fausse alerte de mise à jour n'est générée

---

### Scénario 5 — Droits administrateur manquants lors de la mise à jour
> Un utilisateur sans droits `sudo` tente de mettre à jour l'outil.
> - L'archive est bien téléchargée et décompressée
> - ❌ Le déplacement du nouvel exécutable vers l'emplacement cible échoue (droits insuffisants)
> - L'utilisateur doit relancer la commande avec les droits appropriés

---

# 📈 Score de Clarté Métier : 91/100

**Déductions appliquées :**
- `-5 pts` : Le comportement de RG-03 (absence de contrôle de séniorité de version) est une zone de risque métier non couverte fonctionnellement, signalée mais non résolue dans le code
- `-4 pts` : Le scénario de mise à jour partielle (archive téléchargée mais décompression échouée) mériterait une règle de rollback ou de nettoyage explicite côté produit