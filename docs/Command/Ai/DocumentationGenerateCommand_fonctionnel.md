# Commande de Génération de Documentation Automatique

## Description

Cette commande CLI permet de générer automatiquement deux types de documentation pour n'importe quel fichier source du projet, en utilisant l'intelligence artificielle Claude. Elle produit simultanément une documentation **fonctionnelle** (orientée utilisateur/métier) et une documentation **technique** (orientée développeur).

---

## Utilisation

bash
php bin/console ai:document <chemin_du_fichier> [--format=markdown|json]


### Arguments

| Argument | Obligatoire | Description |
|----------|-------------|-------------|
| `file` | ✅ Oui | Chemin vers le fichier PHP à documenter |

### Options

| Option | Valeur par défaut | Description |
|--------|-------------------|-------------|
| `--format` | `markdown` | Format de sortie des fichiers générés (`markdown` ou `json`) |

---

## Ce que fait la commande

### 1. Validation du fichier source
- Vérifie que le fichier fourni en argument **existe bien** sur le disque
- Retourne une erreur explicite si le fichier est introuvable

### 2. Préparation de l'arborescence de sortie
- Crée automatiquement un dossier miroir dans `docs/` reproduisant la structure du projet source
- Le préfixe `src/` est retiré du chemin pour construire le chemin de destination

### 3. Génération de la documentation fonctionnelle
- Envoie le contenu du fichier à Claude avec un prompt orienté **métier**
- Produit un fichier décrivant **ce que fait le code pour l'utilisateur final**
- Fichier généré : `docs/<chemin_relatif>/<NomFichier>_fonctionnel.md` (ou `.json`)

### 4. Génération de la documentation technique
- Envoie le même contenu à Claude avec un prompt orienté **développeur**
- Produit un fichier décrivant **comment le code fonctionne internement**
- Fichier généré : `docs/<chemin_relatif>/<NomFichier>_technique.md` (ou `.json`)

---

## Fichiers générés

Pour un fichier source `src/Command/Ai/DocumentationGenerateCommand.php`, la commande produira :


docs/
└── Command/
    └── Ai/
        ├── DocumentationGenerateCommand_fonctionnel.md
        └── DocumentationGenerateCommand_technique.md


---

## Gestion des erreurs

| Situation | Comportement |
|-----------|-------------|
| Fichier source introuvable | Message d'erreur rouge + arrêt de la commande |
| Erreur lors de l'appel à Claude | Message d'erreur rouge + arrêt de la commande |

---

## Dépendances

- **Claude AI** : Service d'intelligence artificielle utilisé pour analyser le code et rédiger les documentations
- **Symfony Console** : Framework CLI pour l'exécution de la commande