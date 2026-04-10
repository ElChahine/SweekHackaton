# 📘 Documentation Fonctionnelle

## Module de Mise à Jour Automatique de l'outil CLI sweeek (`swk`)

Ce module permet à l'outil en ligne de commande **swk** (utilisé par les équipes sweeek) de **se mettre à jour automatiquement** vers sa dernière version disponible. Il détecte si une mise à jour est nécessaire, télécharge la bonne version selon l'environnement de la machine, puis remplace l'outil existant sans intervention manuelle complexe.

---

# 📋 Règles de Gestion

## 1. Détection de la version actuelle
- La version actuellement installée de l'outil est lue depuis un fichier interne (`.app.version`).
- Si ce fichier est absent ou illisible, la version retournée est `UNKNOWN`.

## 2. Récupération de la dernière version disponible
- La dernière version disponible est récupérée depuis le dépôt officiel sweeek (GitLab).
- **En cas d'échec de communication avec GitLab** (réseau coupé, service indisponible), la version actuelle est utilisée comme version de référence → **aucune mise à jour ne sera proposée ni déclenchée**, pour éviter tout comportement non souhaité.

## 3. Détection d'une mise à jour disponible
- Une mise à jour est considérée **disponible** si la version distante (GitLab) est **différente** de la version installée localement.
- ⚠️ La comparaison est strictement textuelle : toute différence de valeur entre les deux versions déclenche une mise à jour, quelle que soit la nature du changement (majeur, mineur, correctif).

## 4. Compatibilité plateforme obligatoire
- L'outil identifie automatiquement le **système d'exploitation** de la machine :
  - ✅ Linux → supporté
  - ✅ macOS → supporté
  - ❌ Tout autre système (Windows, etc.) → **mise à jour bloquée avec message d'erreur**
- L'outil identifie également l'**architecture du processeur** :
  - ✅ x86_64 (Intel/AMD classique) → supporté
  - ✅ ARM / ARM64 (Apple Silicon, Raspberry Pi, etc.) → supporté
  - ❌ Toute autre architecture → **mise à jour bloquée**

## 5. Téléchargement et remplacement de l'outil
- Le fichier de mise à jour est téléchargé depuis GitLab **uniquement si la plateforme est supportée**.
- L'archive téléchargée est décompressée automatiquement.
- L'archive temporaire est **supprimée** après décompression (pas de résidu sur la machine).
- Le nouveau fichier `swk` reçoit les **droits d'exécution** nécessaires pour fonctionner.
- Le nouveau fichier `swk` **remplace** l'ancien à son emplacement d'origine, en utilisant les droits administrateur (`sudo`).

## 6. Localisation automatique de l'outil installé
- Le module identifie automatiquement l'emplacement où l'outil `swk` est installé sur la machine afin de le remplacer au bon endroit.
- Si cet emplacement ne peut pas être déterminé, **la mise à jour est bloquée** avec un message d'erreur explicite.

---

# 🚀 Cas d'Usage & Scénarios

## Scénario 1 — L'outil est à jour
> Un développeur sweeek lance une vérification de mise à jour.

- L'outil lit sa version installée (ex : `v1.4.2`).
- Il interroge GitLab : la dernière version disponible est également `v1.4.2`.
- Résultat : **"Aucune mise à jour disponible"** — aucune action n'est effectuée.

---

## Scénario 2 — Une nouvelle version est disponible
> L'équipe sweeek a publié la version `v1.5.0` sur GitLab.

- L'outil détecte que `v1.5.0` ≠ `v1.4.2`.
- Il identifie la machine : Linux, architecture x86_64.
- Il télécharge la version `v1.5.0` adaptée à cet environnement.
- Il décompresse, configure les droits, et remplace l'ancien outil.
- Résultat : **`swk` est maintenant en version `v1.5.0`** sur la machine.

---

## Scénario 3 — Machine non supportée (ex : Windows)
> Un utilisateur tente une mise à jour depuis un poste Windows.

- L'outil détecte le système d'exploitation : Windows.
- Résultat : **Mise à jour bloquée** — message d'erreur "Unsupported platform". L'outil n'est pas modifié.

---

## Scénario 4 — Perte de connexion réseau pendant la vérification
> Le réseau est indisponible au moment de la vérification.

- L'outil tente de contacter GitLab mais échoue.
- Par sécurité, il considère que la version distante = version locale.
- Résultat : **"Aucune mise à jour détectée"** — aucune action, aucune erreur visible pour l'utilisateur.

---

## Scénario 5 — Fichier de version absent ou corrompu
> Le fichier interne `.app.version` est manquant.

- La version locale est indiquée comme `UNKNOWN`.
- Si GitLab retourne une version valide (ex : `v1.5.0`), une mise à jour sera déclenchée car `v1.5.0` ≠ `UNKNOWN`.

---

# 📈 Score de Clarté Métier : 97/100
> **-3 pts** : Le comportement exact en cas d'architecture inconnue (autre qu'x86_64 ou ARM) génère une erreur PHP non explicitement gérée — un cas limite qui mériterait un message d'erreur métier dédié côté UX.