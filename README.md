# 🚀 sweeecli - AI QA & Architecture Assistant

**sweeecli** a été enrichi d'une surcouche d'Intelligence Artificielle (Claude 3.5 Sonnet) pour automatiser les tâches les plus chronophages des Tech Leads et des développeurs : la revue de code, la rédaction de tests complexes, et la documentation.

## 🌟 Fonctionnalités Clés

### 1. 🛡️ Audit de Code Automatisé (CI/CD Ready)
Un Tech Lead virtuel analyse vos diffs Git avant chaque merge.
- Détection des failles de sécurité et des problèmes de performance (ex: Requêtes N+1).
- **Auto-rejet** : Si le score de qualité est inférieur à 80/100, la commande échoue (`FAILURE`), bloquant ainsi les pipelines CI/CD.
- **Usage** : `php index.php ai:review --export`

### 2. 🧪 Génération de Tests (Architecture Multi-Agents)
Ce n'est pas un simple appel à l'IA, c'est un **workflow de 3 agents spécialisés** qui se relayent :
1. **Agent Stratège** : Définit les edge cases et la couverture sans écrire de code.
2. **Agent Data** : Prépare les fixtures et Mocks basés sur la stratégie.
3. **Agent Rédacteur** : Génère le code PHPUnit final.
- **Usage** : `php index.php ai:tests:create src/Core/Updater/Updater.php`

### 3. 📘 Double Documentation (Tech & Métier)
L'outil analyse un dossier entier et génère deux documentations distinctes :
- `README_TECH.md` : Pour les développeurs (Arborescence, flux Mermaid, sécurité).
- `README_FUNC.md` : Pour le Product Owner (Règles métier, cas d'usage).
- **Usage** : `php index.php ai:doc:generate src/Core/Ai`

## 🏗️ Architecture "Prompt-as-Configuration"
Nous avons séparé la logique PHP des instructions IA. Tous les comportements des agents sont modifiables à la volée via des fichiers Markdown situés dans `config/prompts/`, sans jamais toucher au code source.