# Rapport de performance – Projet Orion (CodeVeins)

**Date :** mars 2025  
**Projet :** Orion / CodeVeins  
**Contexte :** Plateforme Symfony 6.4 (PHP 8.2) – gestion des utilisateurs, offres, contrats, tickets, authentification (JWT, face auth), IA (recommandations, support, matching).

---

## 1. Identification du projet

| Élément | Détail |
|--------|--------|
| **Nom** | Orion (CodeVeins) |
| **Stack** | Symfony 6.4, PHP 8.1+ (plateforme 8.2), Doctrine ORM 3.x, Twig, API REST (JWT) |
| **Périmètre analysé** | Code source `src/` (contrôleurs, services, entités, validation métier) |
| **Outils de mesure** | PHPUnit 11 (tests unitaires), PHPStan 2.x (analyse statique niveau 8) |

---

## 2. Objectifs du rapport

- Mesurer la **qualité du code** et la **fiabilité métier** via les tests unitaires et l’analyse statique.
- Documenter les **résultats** (taux de succès des tests, absence d’erreurs PHPStan sur le périmètre configuré).
- Fournir des **indicateurs reproductibles** pour le suivi (commandes, métriques).

---

## 3. Méthodologie

### 3.1 Tests unitaires

- **Framework :** PHPUnit 11.
- **Périmètre :** services de validation métier (un Manager par entité Doctrine).
- **Principe :** tests isolés, sans base de données ni noyau Symfony ; chaque test vérifie qu’une entité valide passe la validation et qu’une entité invalide lève `InvalidArgumentException`.
- **Organisation :**  
  - Services : `src/Service/Validation/<Entité>Manager.php`  
  - Tests : `tests/Service/<Entité>ManagerTest.php`

### 3.2 Analyse statique

- **Outil :** PHPStan (niveau 8 – strict).
- **Périmètre :** répertoire `src/`.
- **Usage :** détection des erreurs de typage, méthodes indéfinies, paramètres incorrects, propriétés non initialisées, etc. Un fichier de baseline (`phpstan-baseline.neon`) permet de figer les erreurs historiques et de viser 0 erreur rapportée tout en réduisant progressivement le baseline.

---

## 4. Résultats

### 4.1 Tests unitaires

| Indicateur | Valeur |
|------------|--------|
| **Nombre de tests (Managers de validation)** | 78 |
| **Nombre d’assertions** | 137 |
| **Résultat** | **OK** (0 échec, 0 erreur) |
| **Entités couvertes** | 19 entités Doctrine |

**Commande d’exécution :**

```bash
php vendor/bin/phpunit tests/Service/ --filter "ManagerTest"
```

**Entités avec service de validation et tests dédiés :**  
User, FaceProfile, UserBan, PasswordResetToken, Ticket, WorkerProfile, WorkerCategory, SubTicket, ServiceRequest, Offer, Notification, Negotiation, Milestone, ConversationMessage, Conversation, Contract, CategoryTicket, AiRecommendation, ServiceRequirement.

Chaque entité dispose d’au moins 2 règles métier vérifiées (champs obligatoires, formats, bornes, dates, statuts).

### 4.2 Analyse statique (PHPStan)

| Indicateur | Valeur |
|------------|--------|
| **Niveau PHPStan** | 8 |
| **Périmètre** | `src/` |
| **Résultat global** | **0 erreur** rapportée |
| **Résultat ciblé Controllers** | 0 erreur |
| **Résultat ciblé Services** | 0 erreur |

**Commandes d’exécution :**

```bash
php vendor/bin/phpstan analyse
php vendor/bin/phpstan analyse src/Controller
php vendor/bin/phpstan analyse src/Service
```

Les erreurs connues mais non bloquantes sont soit listées dans le baseline, soit couvertes par des règles d’ignorance documentées (ex. méthodes `UserInterface::getFullName` / `getUsername`, type de retour des `getAdminSidebarItems`).

---

## 5. Synthèse des indicateurs

| Domaine | Indicateur | Cible | Atteint |
|---------|------------|--------|---------|
| Tests unitaires (validation métier) | 78 tests, 137 assertions | Tous verts | Oui |
| Couverture des entités | 19 entités avec Manager + Test | 100 % des entités métier concernées | Oui |
| Analyse statique | PHPStan level 8 sur `src/` | 0 erreur rapportée | Oui |
| Contrôleurs | PHPStan sur `src/Controller` | 0 erreur | Oui |
| Services | PHPStan sur `src/Service` | 0 erreur | Oui |

---

## 6. Points forts et bonnes pratiques

- **Règles métier centralisées** : la validation est portée par des services dédiés (`*Manager`), ce qui facilite les tests et l’évolution.
- **Tests déterministes** : pas de dépendance à la base ni au conteneur ; résultats reproductibles.
- **Strict typing** : usage de `declare(strict_types=1)` et typage explicite dans les services de validation et les tests.
- **Documentation** : guides dans `docs/` (tests unitaires, analyse statique) avec les commandes et l’organisation du code.

---

## 7. Recommandations et perspectives

- **Réduire le baseline PHPStan** : corriger progressivement le code pour supprimer des entrées de `phpstan-baseline.neon`, puis régénérer avec `php vendor/bin/phpstan analyse --generate-baseline`.
- **Généraliser `getAppUser()`** : dans les contrôleurs, privilégier `$this->getAppUser()` (défini dans `BaseController`) pour bénéficier du typage `User` et limiter les ignores PHPStan.
- **Typage des tableaux** : ajouter des `@return` précis (ex. `list<array{...}>`) pour les méthodes comme `getAdminSidebarItems()` afin de pouvoir retirer les règles d’ignorance.
- **Couverture de code** : activer la couverture (Xdebug + `--coverage-text` ou rapport HTML) pour suivre le pourcentage de code couvert par les tests.

---

## 8. Références documentaires

- **Tests unitaires des entités :** `docs/TESTS_UNITAIRES_ENTITES.md`
- **Analyse statique :** `docs/STATIC_ANALYSIS.md`
- **Configuration PHPStan :** `phpstan.neon` / `phpstan.dist.neon`, `phpstan-baseline.neon`

---

*Rapport généré à partir du dépôt Orion (CodeVeins-main). Les métriques sont reproductibles via les commandes indiquées ci-dessus.*
