# 📘 Documentation Fonctionnelle

## Module : Suite d'outils IA pour la qualité logicielle — sweeek CLI

Ce module regroupe **trois outils automatisés** alimentés par l'intelligence artificielle, accessibles en ligne de commande par les équipes sweeek. Leur objectif commun : **accélérer et fiabiliser le cycle de développement** en automatisant la revue de code, la génération de documentation et la création de tests — des tâches chronophages qui dépendent habituellement d'une expertise humaine.

---

# 📋 Règles de Gestion

## 🔍 Outil 1 — Revue de code par l'IA (`ai:review`)

### Ce que ça fait
Soumet du code à une analyse qualité par intelligence artificielle (Claude) et produit un rapport de validation ou de rejet.

### Modes d'entrée (mutuellement exclusifs)
| Mode | Description |
|---|---|
| **Fichier direct** | Analyse l'intégralité d'un fichier source désigné |
| **Comparaison Git** | Analyse les modifications entre deux versions du code (par défaut : les changements locaux non enregistrés) |

### Règles de gestion — Mode Fichier
- ✅ Le fichier **doit exister** sur le système → sinon : arrêt avec message d'erreur
- ✅ Le fichier **doit être lisible** → sinon : arrêt avec message d'erreur
- ✅ Le fichier **ne doit pas être vide** → sinon : arrêt avec avertissement (succès sans analyse)
- ✅ Le contenu lu est transmis à l'IA accompagné du nom du fichier pour contextualiser l'analyse

### Règles de gestion — Mode Git
- ✅ Par défaut, la comparaison s'effectue sur les modifications locales non enregistrées (`HEAD`)
- ✅ Il est possible de comparer deux branches ou deux points d'historique distincts
- ✅ Si Git rencontre une erreur (branche inexistante, dépôt absent…) → arrêt avec le message d'erreur Git
- ✅ Si **aucune modification** n'est détectée entre les deux points → arrêt avec avertissement (succès sans analyse)

### Règles de gestion — Analyse et verdict
- ✅ Un contexte projet peut être précisé pour affiner l'analyse (ex : "module de paiement", "API publique")
- ✅ L'analyse affiche une **barre de progression** pendant le traitement par l'IA
- ✅ Le rapport produit est affiché directement dans le terminal
- ✅ Si l'option d'export est activée, le rapport est sauvegardé dans `reports/AI_REVIEW.md` (le dossier est créé automatiquement si absent)
- 🚨 **Si le rapport contient le mot-clé `REJECTED`** → le code est considéré comme **rejeté** (score qualité inférieur à 80/100), un message d'alerte est affiché et l'outil se termine en **échec**
- ✅ En l'absence de rejet → le code est considéré **approuvé**, l'outil se termine en succès

---

## 📄 Outil 2 — Génération de documentation (`ai:doc:generate`)

### Ce que ça fait
Génère automatiquement **deux documentations complémentaires** pour un dossier de code :
- Une documentation **technique** (architecture, structure des composants)
- Une documentation **fonctionnelle** (règles métier, cas d'usage)

### Règles de gestion — Paramètres d'entrée
- ✅ Le chemin du dossier à documenter est **obligatoire**
- ✅ Le format de sortie doit être soit `md` (Markdown), soit `json` — tout autre format est refusé avec un message d'erreur
- ✅ Le format par défaut est `md` si non précisé
- ✅ Un contexte optionnel peut être fourni pour orienter la génération

### Règles de gestion — Génération et sauvegarde
- ✅ Les deux documentations sont générées **séquentiellement** (technique d'abord, fonctionnelle ensuite)
- ✅ Les fichiers sont sauvegardés dans un dossier horodaté (`docs/generated/AAAAMMJJ_HHMMSS/`) créé automatiquement
- ✅ En format Markdown : fichiers `README_TECH.md` et `README_FUNC.md`
- ✅ En format JSON : fichiers `README_TECH.json` et `README_FUNC.json`

### Règles de gestion — Format JSON (robustesse)
- ✅ Si l'IA retourne un JSON entouré de balises de mise en forme (ex : bloc de code), ces balises sont automatiquement supprimées avant traitement
- ✅ Si du texte parasite précède le JSON, le système tente d'extraire la partie JSON valide
- ✅ Les caractères spéciaux incompatibles avec le format JSON sont automatiquement nettoyés
- ✅ En cas d'échec persistant de lecture du JSON, un fichier JSON de secours est produit, contenant le contenu brut et un message d'avertissement explicite — **aucune donnée n'est perdue**
- ✅ Si le contenu ne peut pas être converti en structure exploitable, une erreur bloquante est levée

---

## 🧪 Outil 3 — Génération de tests automatisés (`ai:tests:create`)

### Ce que ça fait
Orchestre trois agents IA successifs pour produire des tests automatisés à partir d'un fichier source, sans intervention humaine.

### Règles de gestion — Paramètres d'entrée
- ✅ Le fichier source à tester est **obligatoire**
- ✅ Le type de test à générer doit être précisé : `unit` (tests unitaires) ou `functional` (tests fonctionnels)
- ✅ Le type par défaut est `unit` si non précisé

### Règles de gestion — Chaîne de traitement (3 agents séquentiels)
| Étape | Agent | Rôle |
|---|---|---|
| 1 | **Analyste** | Lit le code source et définit la stratégie de test adaptée |
| 2 | **Préparateur** | Génère les données de test nécessaires (jeux de données) |
| 3 | **Rédacteur** | Produit le code de test final, en s'appuyant sur la stratégie et les données |

- ✅ Chaque étape est visible dans le terminal avec son statut
- ✅ La stratégie définie par l'Agent 1 est affichée avant de passer à l'étape suivante
- ✅ Le code de test final est affiché dans le terminal
- ✅ Si une étape échoue, l'ensemble du processus s'arrête avec un message d'erreur explicite

---

# 🚀 Cas d'Usage & Scénarios

## Scénario 1 — Un développeur soumet une Pull Request

> Avant de pousser ses modifications, un développeur lance la revue IA en comparant sa branche de travail avec la branche principale.

**Résultat attendu :** L'IA analyse les différences, produit un rapport de qualité. Si le score est insuffisant (< 80/100), le développeur reçoit une alerte et des recommandations concrètes avant de pouvoir valider son travail.

---

## Scénario 2 — Un PO veut documenter un nouveau module

> Un dossier contenant les règles de gestion d'un panier d'achat vient d'être finalisé. Le PO demande une documentation complète.

**Résultat attendu :** En une commande, deux fichiers sont produits : un document technique pour l'équipe dev, un document fonctionnel réutilisable pour les specs, le support et la QA.

---

## Scénario 3 — La QA veut couvrir un module non testé

> Un fichier gérant les promotions n'a aucun test. L'équipe QA souhaite générer une base de tests fonctionnels rapidement.

**Résultat attendu :** Les trois agents IA analysent le fichier, préparent les scénarios de données (cas normaux, cas limites), et produisent un fichier de test prêt à être intégré au projet.

---

## Scénario 4 — Intégration dans la chaîne de validation continue

> À chaque fusion de branche, la revue IA est exécutée automatiquement. Si le mot-clé `REJECTED` apparaît dans le rapport, la fusion est bloquée.

**Résultat attendu :** Zéro code de mauvaise qualité ne passe en production sans avoir été signalé et corrigé.

---

# 📈 Score de Clarté Métier : 97/100

**Justification :**
- ✅ Toutes les règles de gestion métier identifiées dans le code sont documentées de manière exhaustive
- ✅ Aucun jargon technique (aucune mention d'injection, de classe, de méthode, de regex…)
- ✅ Les tableaux et la structure facilitent la lecture pour des profils non-techniques
- ✅ Les scénarios sont ancrés dans des situations réelles du quotidien sweeek
- **-3 pts** : Le comportement exact du rapport en cas d'erreur IA imprévue mériterait un scénario dédié pour le support client