# 📘 Documentation Fonctionnelle — Suite d'outils IA pour développeurs sweeek

## Vue d'ensemble

sweeek met à disposition de ses équipes de développement une suite de **trois assistants intelligents** accessibles en ligne de commande. Ces outils utilisent l'intelligence artificielle (Claude) pour automatiser trois tâches chronophages du quotidien : **la revue de code**, **la génération de documentation** et **la création de tests automatisés**. L'objectif est d'élever le niveau de qualité logicielle de façon systématique, sans dépendre uniquement de la vigilance humaine.

---

# 📋 Règles de Gestion

## 🔍 Outil 1 — Revue de code par l'IA (`ai:review`)

Cet outil soumet du code à une analyse qualité automatisée par l'IA et produit un rapport d'évaluation.

### Ce que fait cet outil
Il récupère les modifications de code (soit depuis l'historique Git, soit depuis un fichier donné), les envoie à l'IA pour analyse, puis affiche un rapport de qualité. Il peut également bloquer la progression si le code est jugé insuffisant.

### Règles de fonctionnement

**Mode 1 — Analyse d'un fichier spécifique**
- ✅ L'utilisateur peut demander l'analyse complète d'un fichier précis en indiquant son chemin.
- ❌ Si le fichier indiqué n'existe pas à l'emplacement fourni → erreur bloquante, l'analyse est annulée.
- ❌ Si le fichier existe mais ne peut pas être ouvert (droits insuffisants) → erreur bloquante.
- ❌ Si le fichier est vide → avertissement non bloquant, l'analyse est ignorée et considérée comme réussie.

**Mode 2 — Analyse des modifications Git (mode par défaut)**
- ✅ Par défaut, l'outil compare les modifications locales non encore enregistrées (non commitées).
- ✅ Il est possible de comparer deux versions précises du code (ex : branche de développement vs branche principale).
- ❌ Si Git retourne une erreur (branche inexistante, dépôt non initialisé…) → erreur bloquante avec détail du problème Git.
- ❌ Si aucune modification n'est détectée entre les deux versions → avertissement non bloquant, l'analyse est ignorée et considérée comme réussie.

**Analyse et résultat**
- ✅ Un contexte métier peut être précisé pour affiner la pertinence de l'analyse IA (ex : "module de paiement", "gestion des stocks").
- ✅ Une barre de progression s'affiche pendant l'analyse pour informer l'utilisateur que le traitement est en cours.
- ✅ Le rapport complet est affiché dans le terminal à l'issue de l'analyse.

**Export du rapport**
- ✅ Sur demande explicite, le rapport est exporté dans un fichier `reports/AI_REVIEW.md`.
- ✅ Si le dossier `reports/` n'existe pas, il est créé automatiquement.

**Règle de rejet qualité — Règle critique ⚠️**
- ❌ Si le rapport contient le verdict `REJECTED` (score de qualité inférieur à 80/100), l'outil retourne un **statut d'échec** accompagné d'une alerte rouge visible.
  - Un message d'avertissement invite le développeur à corriger le code avant de l'enregistrer.
  - Ce statut d'échec peut être utilisé pour **bloquer automatiquement** une pipeline d'intégration continue (CI/CD).
- ✅ Si le code est approuvé (score ≥ 80/100), un message de succès vert est affiché.

**Gestion des erreurs IA**
- ❌ Si l'IA est indisponible ou retourne une erreur → message d'erreur explicite et statut d'échec.

---

## 📄 Outil 2 — Génération automatique de documentation (`ai:doc:generate`)

Cet outil analyse un dossier de code et produit automatiquement **deux types de documentation complémentaires**.

### Ce que fait cet outil
Il parcourt un dossier désigné et génère, grâce à l'IA, deux fichiers de documentation prêts à l'emploi : l'un destiné aux équipes techniques, l'autre aux équipes métier.

### Règles de fonctionnement

**Entrée obligatoire**
- ✅ Un dossier à documenter doit obligatoirement être fourni — sans lui, l'outil refuse de démarrer.
- ✅ Un contexte optionnel peut être précisé pour orienter la rédaction (ex : "module de livraison", "gestion des retours").

**Génération des deux documentations**
- ✅ L'IA produit **systématiquement deux documents distincts** pour chaque appel :
  1. **Documentation technique** (`README_TECH.md`) : décrit l'architecture, les composants et leur organisation interne. Destinée aux développeurs.
  2. **Documentation fonctionnelle** (`README_FUNC.md`) : décrit les règles métier et les cas d'usage. Destinée aux équipes PO, QA et Support.

**Stockage des fichiers générés**
- ✅ Chaque génération crée un nouveau dossier horodaté (ex : `docs/generated/20250115_143022/`) afin de **ne jamais écraser une documentation précédente**.
- ✅ Si le dossier de destination n'existe pas, il est créé automatiquement.

**Gestion des erreurs**
- ❌ Si l'IA échoue lors de l'une ou l'autre des générations → message d'erreur explicite et statut d'échec global.

---

## 🧪 Outil 3 — Génération automatique de tests (`ai:tests:create`)

Cet outil analyse un fichier de code et génère automatiquement les tests correspondants via un processus en trois étapes coordonnées.

### Ce que fait cet outil
Il orchestre trois agents IA spécialisés qui travaillent en séquence pour produire des tests de qualité, adaptés au type de vérification souhaité (unitaire ou fonctionnel).

### Règles de fonctionnement

**Entrée obligatoire**
- ✅ Un fichier source à tester doit être fourni — sans lui, l'outil refuse de démarrer.
- ✅ Le type de test peut être précisé : `unit` (vérifie un composant isolé) ou `functional` (vérifie un enchaînement de comportements). Par défaut : `unit`.

**Processus en trois étapes séquentielles**

> Les trois étapes sont interdépendantes : chacune alimente la suivante. Si l'une échoue, les suivantes sont annulées.

- **Étape 1 — Définition de la stratégie de test**
  - ✅ Un premier agent IA lit le fichier source et détermine *quoi* tester et *comment* le tester.
  - ✅ La stratégie retenue est affichée dans le terminal pour permettre à l'équipe de valider l'approche.

- **Étape 2 — Préparation des données de test (fixtures)**
  - ✅ Un deuxième agent IA génère les jeux de données nécessaires pour faire tourner les tests (ex : un panier simulé, un utilisateur fictif, une commande de test).
  - Ces données sont transmises à l'étape suivante.

- **Étape 3 — Rédaction du code de test**
  - ✅ Un troisième agent IA rédige le code de test final, en s'appuyant sur la stratégie et les données des étapes précédentes.
  - ✅ Le code généré est affiché dans le terminal et un message de succès confirme la réussite du processus complet.

**Gestion des erreurs**
- ❌ Si l'un des trois agents IA échoue → message d'erreur explicite et arrêt immédiat du processus.

---

# 🚀 Cas d'Usage & Scénarios

## Scénario 1 — Un développeur soumet ses modifications pour validation avant enregistrement

> Marie vient de modifier le moteur de calcul des frais de livraison. Avant de soumettre ses changements, elle lance la revue IA sur ses modifications locales. L'IA détecte un oubli de gestion d'un cas limite (livraison express sans adresse complète) et attribue un score de 65/100. L'outil affiche une alerte rouge et retourne un statut d'échec. Marie corrige le cas manquant, relance la revue — score 88/100, code approuvé.

## Scénario 2 — La pipeline CI/CD bloque automatiquement un code de mauvaise qualité

> Un développeur soumet une Pull Request. La pipeline automatisée lance `ai:review` en comparant la branche de travail avec la branche principale. Le score est de 72/100, le rapport contient `REJECTED`. La pipeline échoue, la PR ne peut pas être fusionnée tant que les correctifs n'ont pas été appliqués.

## Scénario 3 — Un PO demande la documentation d'un nouveau module

> L'équipe vient de livrer le module "Gestion des retours". Le PO lance la génération de documentation sur ce dossier avec le contexte "module retours e-commerce". En quelques minutes, deux fichiers sont disponibles : un pour les développeurs (architecture), un pour le Support et la QA (règles métier et cas d'usage). Un nouveau dossier horodaté est créé, les documentations précédentes restent accessibles.

## Scénario 4 — Un développeur génère les tests d'un nouveau service métier

> Thomas vient de créer un service de calcul de remises promotionnelles. Plutôt que d'écrire les tests manuellement, il lance `ai:tests:create` sur ce fichier en mode `unit`. L'IA analyse le service, identifie 8 cas à tester (remise 0%, remise cumulée, plafond dépassé…), prépare les données de simulation, puis produit le code de test complet. Thomas n'a plus qu'à relire et ajuster si nécessaire.

## Scénario 5 — Analyse ciblée d'un fichier de configuration sensible

> L'équipe souhaite faire auditer le fichier de règles de TVA avant la mise en production. Un développeur lance `ai:review --file src/Tax/TaxRules.php --export`. L'IA analyse le fichier entier (et non les seules modifications récentes), produit un rapport détaillé, et l'exporte dans `reports/AI_REVIEW.md` pour archivage et partage avec l'équipe.

---

# 📈 Score de Clarté Métier : 97/100

**Justification :** Documentation exhaustive couvrant l'ensemble des règles de gestion des trois outils, y compris tous les cas d'échec et de succès. Aucun jargon technique. Les scénarios couvrent des situations réelles de l'écosystème e-commerce sweeek. Légère marge conservée pour les éventuels comportements internes du moteur d'analyse (DocAnalyzer, TestAnalyzer) non documentés dans le code fourni.