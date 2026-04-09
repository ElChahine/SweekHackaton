# 🚀 sweeecli - AI QA & Architecture Assistant

**sweeecli** a été enrichi d'une surcouche d'Intelligence Artificielle (Claude 3.5 Sonnet) pour automatiser les tâches les plus chronophages des Tech Leads et des développeurs : la revue de code, la rédaction de tests complexes, et la documentation.

## 🌟 Fonctionnalités Clés

### 1. 🛡️ Audit de Code Automatisé (CI/CD Ready)
Un Tech Lead virtuel analyse vos diffs Git avant chaque merge.
- Détection des failles de sécurité et des problèmes de performance (ex: Requêtes N+1).
- **Auto-rejet** : Si le score de qualité est inférieur à 80/100, la commande échoue (`FAILURE`), bloquant ainsi les pipelines CI/CD.

### 2. 🧪 Génération de Tests (Architecture Multi-Agents)
Ce n'est pas un simple appel à l'IA, c'est un **workflow de 3 agents spécialisés** qui se relayent :
1. **Agent Stratège** : Définit les edge cases et la couverture sans écrire de code.
2. **Agent Data** : Prépare les fixtures et Mocks basés sur la stratégie.
3. **Agent Rédacteur** : Génère le code PHPUnit final.

### 3. 📘 Double Documentation (Tech & Métier)
L'outil analyse un dossier entier et génère deux documentations distinctes :
- `README_TECH.md` : Pour les développeurs (Arborescence, flux Mermaid, sécurité).
- `README_FUNC.md` : Pour le Product Owner (Règles métier, cas d'usage).

---

## 🛠️ Référence des Commandes & Options

### `ai:review` (Audit de Code)
Analyse un diff Git ou un fichier spécifique et applique un barème strict sur 100.
**Usage de base :** `php index.php ai:review` (analyse le diff local avec HEAD)
* `[base]` : La branche ou le commit de base (défaut : `HEAD`).
* `[target]` : La branche cible à comparer (optionnel).
* `--file=...` ou `-f` : Analyser un fichier précis au lieu du diff Git.
* `--context=...` ou `-c` : Ajouter un contexte projet spécifique pour guider l'IA.
* `--export` ou `-e` : Sauvegarder le rapport généré dans `reports/AI_REVIEW.md`.

### `ai:tests:create` (Workflow Multi-Agents)
Génère une suite de tests prête à l'emploi en orchestrant 3 agents IA.
**Usage de base :** `php index.php ai:tests:create src/MonFichier.php`
* `file` (Requis) : Le chemin vers le fichier PHP à tester.
* `--type=...` ou `-t` : Définir la cible du test (`unit` ou `functional`). Par défaut : `unit`.

### `ai:doc:generate` (Double Documentation)
Génère la documentation technique et fonctionnelle pour un répertoire entier.
**Usage de base :** `php index.php ai:doc:generate src/MonDossier`
* `directory` (Requis) : Le chemin vers le dossier à documenter.
* `--context=...` ou `-c` : Ajouter des précisions (ex: "Ce dossier gère la logistique").

---

## 🏗️ Architecture "Prompt-as-Configuration"
Nous avons séparé la logique PHP des instructions IA. Tous les comportements des agents sont modifiables à la volée via des fichiers Markdown situés dans `config/prompts/`, sans jamais toucher au code source.