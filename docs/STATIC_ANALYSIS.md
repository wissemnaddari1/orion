# Analyse statique (PHPStan)

Ce document décrit l’utilisation de **PHPStan** dans le projet (Symfony 6.4).

---

## Installation

PHPStan est déjà en dépendance de dev :

```bash
composer require --dev phpstan/phpstan
```

Vérifier la version :

```bash
php vendor/bin/phpstan --version
```

---

## Configuration

- **Fichier principal** : `phpstan.neon` à la racine (ou `phpstan.dist.neon` si vous préférez versionner la config par défaut).
- **Niveau** : **8** (strict).
- **Cible** : répertoire `src/`.
- **Baseline** : `phpstan-baseline.neon` — les erreurs historiques y sont listées pour ne pas casser la CI ; à réduire au fil des corrections.

Contenu type de `phpstan.neon` :

- `level: 8`, `paths: [src]`
- `reportUnmatchedIgnoredErrors: false` (évite les avertissements quand on lance l’analyse sur un sous-dossier).
- `ignoreErrors` : motifs temporaires (ex. `UserInterface::getFullName` / `getUsername`, `getAdminSidebarItems` sans type de valeur).
- `includes: [phpstan-baseline.neon]`

---

## Commandes à exécuter

**Analyse complète de `src/` :**

```bash
php vendor/bin/phpstan analyse
```

**Analyse ciblée :**

```bash
php vendor/bin/phpstan analyse src/Controller
php vendor/bin/phpstan analyse src/Service
```

**Résultat attendu (état actuel) :**

- `[OK] No errors` pour l’analyse complète et pour `src/Controller` et `src/Service`.

---

## Réduire le baseline

Pour corriger des erreurs et les retirer du baseline :

1. Corriger le code.
2. Relancer : `php vendor/bin/phpstan analyse`.
3. Régénérer le baseline :  
   `php vendor/bin/phpstan analyse --generate-baseline`  
   (remplace `phpstan-baseline.neon` par les erreurs restantes).

---

## Erreurs ignorées temporairement (phpstan.neon)

- **UserInterface::getFullName() / getUsername()** : l’entité `User` implémente ces méthodes, mais PHPStan ne le voit pas sur le type `UserInterface`. À terme, utiliser `getAppUser()` (défini dans `BaseController`) dans les contrôleurs.
- **getAdminSidebarItems() return type** : les tableaux de retour n’ont pas de type de valeur (iterable). À terme, ajouter un `@return list<array{...}>` (ou équivalent) sur chaque méthode.

Ces règles sont documentées en TODO dans `phpstan.neon`.

---

## Résumé

| Commande | Usage |
|----------|--------|
| `php vendor/bin/phpstan analyse` | Analyse tout `src/` |
| `php vendor/bin/phpstan analyse src/Controller` | Contrôleurs uniquement |
| `php vendor/bin/phpstan analyse src/Service` | Services uniquement |
| `php vendor/bin/phpstan analyse --generate-baseline` | Régénère le baseline après corrections |

**État actuel** : 0 erreur rapportée (les erreurs connues sont dans le baseline ou dans `ignoreErrors`). Le nombre d’entrées dans le baseline (566 à la génération initiale) peut être réduit en corrigeant le code puis en régénérant le baseline.
