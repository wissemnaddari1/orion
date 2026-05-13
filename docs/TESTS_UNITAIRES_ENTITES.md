# Tests unitaires des entités (Validation Managers)

Ce document décrit comment exécuter les tests, comment travailler avec eux, et quelles entités sont couvertes.

---

## 1. Comment exécuter les tests

### Tous les tests du projet

```bash
php vendor/bin/phpunit
```

### Uniquement les tests des Managers de validation (entités)

```bash
php vendor/bin/phpunit tests/Service/ --filter "ManagerTest"
```

### Un seul Manager (exemple : User)

```bash
php vendor/bin/phpunit tests/Service/UserManagerTest.php
```

### Un seul test (exemple : testValidUser)

```bash
php vendor/bin/phpunit tests/Service/UserManagerTest.php --filter testValidUser
```

### Rapport lisible (testdox)

Affiche une description en langage naturel de chaque test :

```bash
php vendor/bin/phpunit tests/Service/ --filter "ManagerTest" --testdox
```

Exemple de sortie (extrait) :

```
User Manager (App\Tests\Service\UserManager)
 ✔ Valid user
 ✔ User without email
 ✔ User with invalid email
 ✔ User without username
 ...
OK (78 tests, 137 assertions)
```

### Avec couverture (si Xdebug est installé)

```bash
php vendor/bin/phpunit tests/Service/ --filter "ManagerTest" --coverage-text
```

---

## 2. Comment travailler avec ces tests

### Workflow recommandé

1. **Avant de modifier une règle métier** : lancer les tests du Manager concerné pour vérifier l’état actuel.
2. **Après modification** : relancer les tests pour s’assurer qu’aucune régression n’a été introduite.
3. **Pour un rapport rapide** : utiliser `--testdox` pour voir la liste des scénarios couverts.

### Où sont les fichiers

| Rôle | Emplacement | Namespace |
|------|-------------|-----------|
| Service de validation | `src/Service/Validation/<EntityName>Manager.php` | `App\Service\Validation` |
| Tests unitaires | `tests/Service/<EntityName>ManagerTest.php` | `App\Tests\Service` |

### Structure d’un test

- **`makeValid<EntityName>()`** : méthode privée qui construit une instance valide (pour éviter la duplication).
- **`testValid<EntityName>()`** : vérifie que `validate($entity)` retourne `true`.
- **`test<EntityName>Without<Champ>()`** : vérifie qu’une entité sans champ requis lève `InvalidArgumentException`.
- **`test<EntityName>WithInvalid<Chose>()`** : vérifie qu’une valeur invalide (email, date, montant, etc.) lève `InvalidArgumentException`.

Les tests sont **unitaires** : pas de base de données, pas de `KernelTestCase`, pas d’intégration. Seule la logique du Manager est testée.

---

## 3. Entités couvertes et correspondance

Chaque entité Doctrine listée ci‑dessous possède :

- un **service de validation** : `src/Service/Validation/<EntityName>Manager.php` ;
- une **classe de test** : `tests/Service/<EntityName>ManagerTest.php`.

| # | Entité | Fichier Manager | Fichier Test |
|---|--------|-----------------|--------------|
| 1 | **User** | `UserManager.php` | `UserManagerTest.php` |
| 2 | **FaceProfile** | `FaceProfileManager.php` | `FaceProfileManagerTest.php` |
| 3 | **UserBan** | `UserBanManager.php` | `UserBanManagerTest.php` |
| 4 | **PasswordResetToken** | `PasswordResetTokenManager.php` | `PasswordResetTokenManagerTest.php` |
| 5 | **Ticket** | `TicketManager.php` | `TicketManagerTest.php` |
| 6 | **WorkerProfile** | `WorkerProfileManager.php` | `WorkerProfileManagerTest.php` |
| 7 | **WorkerCategory** | `WorkerCategoryManager.php` | `WorkerCategoryManagerTest.php` |
| 8 | **SubTicket** | `SubTicketManager.php` | `SubTicketManagerTest.php` |
| 9 | **ServiceRequest** | `ServiceRequestManager.php` | `ServiceRequestManagerTest.php` |
| 10 | **Offer** | `OfferManager.php` | `OfferManagerTest.php` |
| 11 | **Notification** | `NotificationManager.php` | `NotificationManagerTest.php` |
| 12 | **Negotiation** | `NegotiationManager.php` | `NegotiationManagerTest.php` |
| 13 | **Milestone** | `MilestoneManager.php` | `MilestoneManagerTest.php` |
| 14 | **ConversationMessage** | `ConversationMessageManager.php` | `ConversationMessageManagerTest.php` |
| 15 | **Conversation** | `ConversationManager.php` | `ConversationManagerTest.php` |
| 16 | **Contract** | `ContractManager.php` | `ContractManagerTest.php` |
| 17 | **CategoryTicket** | `CategoryTicketManager.php` | `CategoryTicketManagerTest.php` |
| 18 | **AiRecommendation** | `AiRecommendationManager.php` | `AiRecommendationManagerTest.php` |
| 19 | **ServiceRequirement** | `ServiceRequirementManager.php` | `ServiceRequirementManagerTest.php` |

**Total : 19 entités**, 19 Managers, 19 TestCases (plus de 78 tests au total).

---

## 4. Rapport attendu après exécution

En exécutant :

```bash
php vendor/bin/phpunit tests/Service/ --filter "ManagerTest"
```

le résultat attendu est du type :

```
PHPUnit 11.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.x
Configuration: .../phpunit.dist.xml

......................................................................... 78 / 78 (100%)

Time: 00:00.xxx, Memory: xx.xx MB

OK (78 tests, 137 assertions)
```

- **OK** : tous les tests sont verts.
- En cas d’échec : PHPUnit affiche le test en erreur, le fichier et la ligne, ainsi que le message d’assertion ou l’exception attendue.

---

## 5. Exemple de rapport détaillé (--testdox)

En lançant `php vendor/bin/phpunit tests/Service/ --filter "ManagerTest" --testdox`, vous obtenez la liste des scénarios par Manager, par exemple :

- **AiRecommendationManager** : valid, without service request, score out of range
- **CategoryTicketManager** : valid, without name
- **ContractManager** : valid, without title/scope/client, start after end, negative price
- **ConversationManager** : valid, without contract/client
- **ConversationMessageManager** : valid, without content/conversation
- **FaceProfileManager** : valid, without user, empty embedding
- **MilestoneManager** : valid, without title/contract, negative order/amount
- **NegotiationManager** : valid, without offer, negative counter price
- **NotificationManager** : valid, without user/title, invalid type
- **OfferManager** : valid, negative price, invalid estimated time, without service request/worker
- **PasswordResetTokenManager** : valid, expires before requested
- **ServiceRequestManager** : valid, without title/client/category, title too short, budget min>max, invalid duration
- **ServiceRequirementManager** : valid, without title/details, negative priority
- **SubTicketManager** : valid, without message/ticket
- **TicketManager** : valid, without subject/category, invalid status
- **UserBanManager** : valid, without reason, temp without endsAt, endsAt before bannedAt
- **UserManager** : valid, without email/username/firstName/lastName, invalid email/username, username too short
- **WorkerCategoryManager** : valid, without name/description, negative display order/hourly rate
- **WorkerProfileManager** : valid, without title/bio, negative hourly rate

---

## 6. Résumé

- **Exécution** : `php vendor/bin/phpunit tests/Service/ --filter "ManagerTest"`.
- **Rapport lisible** : ajouter `--testdox`.
- **Entités couvertes** : les 19 entités listées dans le tableau ci‑dessus (User, FaceProfile, UserBan, PasswordResetToken, Ticket, WorkerProfile, WorkerCategory, SubTicket, ServiceRequest, Offer, Notification, Negotiation, Milestone, ConversationMessage, Conversation, Contract, CategoryTicket, AiRecommendation, ServiceRequirement).
- **Règles métier** : chaque Manager applique au moins 2 règles (champs obligatoires, formats, bornes, dates, statuts). Les validations sont dans les services, pas dans les entités.

---
