# Scénarios de test – Projet Orion (CodeVeins)

Ce document décrit les **scénarios de test** pour couvrir l’ensemble du projet Orion (authentification, rôles Client / Worker / Admin, demandes de service, offres, contrats, tickets, messagerie, profil, API, paiement).  
Ils peuvent servir pour des **tests manuels** ou comme base pour des **tests d’intégration / E2E**.

---

## 1. Rôles et accès

| Rôle | Périmètre |
|------|-----------|
| **Client** | Dashboard client, demandes de service, offres reçues, contrats, tickets, messagerie, profil |
| **Worker (Freelancer)** | Dashboard worker, offres créées, contrats, profil (CV, certificat), tickets, messagerie |
| **Admin** | Dashboard admin, utilisateurs, catégories (worker + ticket), offres, services, contrats, certificats, logs face auth |
| **Super Admin** | Hérite de Admin |

---

## 2. Authentification

### AUTH-01 – Connexion (login) avec email et mot de passe

- **Rôle :** Utilisateur avec compte actif
- **Préconditions :** Compte existant, email vérifié, statut ACTIVE
- **Étapes :**
  1. Aller sur `/login`
  2. Saisir email et mot de passe valides
  3. Soumettre le formulaire (ou appel API `POST /api/login` avec JSON `email`, `password`)
- **Résultat attendu :** Redirection vers le dashboard selon le rôle (client / worker / admin) ou retour JWT en JSON ; pas d’erreur 401

### AUTH-02 – Connexion avec identifiants invalides

- **Rôle :** Visiteur
- **Étapes :**
  1. Aller sur `/login` ou `POST /api/login`
  2. Saisir un email ou mot de passe incorrect
- **Résultat attendu :** Message d’erreur « Invalid credentials » (ou équivalent) ; pas de redirection vers un espace protégé

### AUTH-03 – Connexion avec compte verrouillé (trop de tentatives)

- **Préconditions :** Compte avec login lock actif (`login_locked_until`)
- **Étapes :**
  1. Tenter de se connecter avec les bons identifiants
- **Résultat attendu :** Réponse 429 ou message indiquant que le compte est temporairement verrouillé

### AUTH-04 – Connexion avec compte banni

- **Préconditions :** Utilisateur avec bannissement actif
- **Étapes :**
  1. Se connecter avec email / mot de passe
- **Résultat attendu :** Message du type « account restricted » / « contact support » ; redirection possible vers `/banned` ou équivalent

### AUTH-05 – Inscription client

- **Rôle :** Visiteur
- **Étapes :**
  1. Aller sur `/register` puis choisir inscription client ou aller sur `/register/client`
  2. Remplir le formulaire (email, mot de passe, nom, prénom, etc.)
  3. Soumettre
- **Résultat attendu :** Compte créé avec rôle CLIENT ; message demandant de vérifier l’email (si vérification activée)

### AUTH-06 – Inscription freelancer (worker)

- **Rôle :** Visiteur
- **Étapes :**
  1. Aller sur `/register/freelancer`
  2. Remplir le formulaire (email, mot de passe, nom, prénom, etc.)
  3. Soumettre
- **Résultat attendu :** Compte créé avec rôle WORKER ; message pour vérification email si activée

### AUTH-07 – Vérification d’email

- **Préconditions :** Compte créé, email non vérifié
- **Étapes :**
  1. Cliquer sur le lien reçu par email (ou aller sur `/verify-email` avec le token fourni)
  2. Soumettre si formulaire
- **Résultat attendu :** Compte marqué comme email vérifié ; possibilité de se connecter

### AUTH-08 – Renvoi du lien de vérification

- **Préconditions :** Compte non vérifié
- **Étapes :**
  1. Aller sur la page de vérification ou `/resend-verification`
  2. Demander un renvoi (bouton / `POST /resend-verification`)
- **Résultat attendu :** Nouveau mail envoyé (ou message de limitation si rate-limit)

### AUTH-09 – Mot de passe oublié (demande)

- **Rôle :** Visiteur
- **Étapes :**
  1. Aller sur `/forgot-password`
  2. Saisir l’email du compte
  3. Soumettre
- **Résultat attendu :** Message indiquant qu’un lien a été envoyé si l’email existe (sans révéler l’existence du compte si politique de sécurité)

### AUTH-10 – Réinitialisation du mot de passe (lien reçu)

- **Préconditions :** Token de reset valide reçu par email
- **Étapes :**
  1. Ouvrir le lien `/reset-password?token=...`
  2. Saisir le nouveau mot de passe (et confirmation)
  3. Soumettre
- **Résultat attendu :** Mot de passe mis à jour ; possibilité de se connecter avec le nouveau mot de passe

### AUTH-11 – Connexion avec authentification faciale (face login)

- **Préconditions :** Utilisateur déjà enregistré en face (profil face enroll)
- **Étapes :**
  1. Aller sur `/auth/face/login` ou déclencher `POST /api/face-login` avec les données biométriques
  2. Compléter la vérification face
- **Résultat attendu :** Connexion réussie si le visage correspond ; sinon message d’échec

### AUTH-12 – Enregistrement du visage (face enroll)

- **Préconditions :** Utilisateur connecté (JWT)
- **Étapes :**
  1. Aller sur `/auth/face/enroll` ou `/profile/face` et suivre le flux d’enregistrement
  2. Envoyer les données d’enrôlement (ex. `POST /api/face/enroll`)
- **Résultat attendu :** Profil face créé/mis à jour ; possibilité d’utiliser le face login ensuite

### AUTH-13 – 2FA (vérification TOTP)

- **Préconditions :** Compte avec 2FA activé, connexion première étape réussie
- **Étapes :**
  1. Après login, être redirigé ou afficher la page 2FA (`/2fa`)
  2. Saisir le code TOTP (app ou SMS)
  3. Soumettre (ex. `POST /api/auth/2fa/verify`)
- **Résultat attendu :** Connexion complète et JWT émis

### AUTH-14 – Déconnexion

- **Préconditions :** Utilisateur connecté
- **Étapes :**
  1. Cliquer sur Déconnexion ou appeler `GET/POST /logout` ou `POST /api/logout`
- **Résultat attendu :** Session / token invalide ; redirection vers la page d’accueil ou login

---

## 3. Client – Demandes de service (Service Request)

### SR-01 – Créer une demande de service

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/request/new`
  2. Remplir titre (min 5 car.), description (min 20 car.), budget min/max, durée, catégorie
  3. Soumettre
- **Résultat attendu :** Demande créée ; redirection vers la liste ou la fiche de la demande

### SR-02 – Créer une demande invalide (validation)

- **Rôle :** Client
- **Étapes :**
  1. Sur `/request/new`, saisir un titre trop court ou une description trop courte ou budget min ≥ max
  2. Soumettre
- **Résultat attendu :** Messages d’erreur affichés ; demande non créée

### SR-03 – Lister ses demandes

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/requests`
- **Résultat attendu :** Liste des demandes du client connecté

### SR-04 – Voir une demande

- **Rôle :** Client
- **Étapes :**
  1. Depuis la liste, cliquer sur une demande ou aller sur `/request/{id}`
- **Résultat attendu :** Détail de la demande (titre, description, budget, catégorie, etc.)

### SR-05 – Modifier une demande

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/requests/edit/{id}` (ou équivalent)
  2. Modifier des champs et soumettre
- **Résultat attendu :** Demande mise à jour ; message de succès

### SR-06 – Supprimer une demande

- **Rôle :** Client
- **Étapes :**
  1. Sur la fiche ou la liste, déclencher la suppression (`/request/delete/{id}` POST)
- **Résultat attendu :** Demande supprimée ; redirection vers la liste

### SR-07 – Démarrer un service (start)

- **Rôle :** Client
- **Préconditions :** Demande avec statut autorisant « start »
- **Étapes :**
  1. Sur `/requests/{id}/start` (POST) ou bouton dédié
- **Résultat attendu :** Statut de la demande mis à jour selon la règle métier

---

## 4. Client – Offres

### OFF-01 – Lister les offres reçues (client)

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/client/offers`
- **Résultat attendu :** Liste des offres liées aux demandes du client

### OFF-02 – Voir une offre

- **Rôle :** Client
- **Étapes :**
  1. Cliquer sur une offre ou aller sur `/client/offers/{id}`
- **Résultat attendu :** Détail de l’offre (worker, prix, délai, etc.)

### OFF-03 – Accepter une offre (client)

- **Rôle :** Client
- **Préconditions :** Offre en statut acceptable
- **Étapes :**
  1. Sur la fiche offre, cliquer sur Accepter (`POST /client/offers/{id}/accept`)
- **Résultat attendu :** Offre acceptée ; contrat créé (ou en projet) ; message de succès

### OFF-04 – Négocier une offre (client)

- **Rôle :** Client
- **Étapes :**
  1. Sur la fiche offre, choisir Négocier et envoyer un contre-prix ou message (`POST /client/offers/{id}/negotiate`)
- **Résultat attendu :** Négociation enregistrée ; statut / historique mis à jour

### OFF-05 – Abandonner une négociation (client)

- **Rôle :** Client
- **Étapes :**
  1. Sur une offre en négociation, déclencher l’abandon (`POST /client/offers/{id}/abort-negotiation`)
- **Résultat attendu :** Négociation terminée ; statut cohérent

### OFF-06 – Rejeter une offre (client)

- **Rôle :** Client
- **Étapes :**
  1. Sur la fiche offre, cliquer sur Rejeter (`POST /client/offers/{id}/reject`)
- **Résultat attendu :** Offre rejetée ; message de confirmation

### OFF-07 – Comparer des offres (client)

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/client/offers/compare/{id}` (ou page de comparaison)
- **Résultat attendu :** Affichage de la comparaison (prix, délais, worker, etc.)

---

## 5. Client – Contrats

### CTR-01 – Lister les contrats (client)

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/client/contracts`
- **Résultat attendu :** Liste paginée des contrats du client

### CTR-02 – Voir un contrat (client)

- **Rôle :** Client
- **Étapes :**
  1. Cliquer sur un contrat ou aller sur `/client/contracts/{id}`
- **Résultat attendu :** Détail du contrat (titre, scope, parties, statut, jalons)

### CTR-03 – Télécharger le PDF d’un contrat (client)

- **Rôle :** Client
- **Étapes :**
  1. Sur la fiche contrat, cliquer sur Télécharger PDF (`/client/contracts/{id}/pdf`)
- **Résultat attendu :** Fichier PDF téléchargé ou affiché

### CTR-04 – Envoyer le contrat pour signature (client)

- **Rôle :** Client
- **Préconditions :** Contrat en statut DRAFT
- **Étapes :**
  1. Cliquer sur « Envoyer pour signature » (`POST /client/contracts/{id}/send-for-signing`)
- **Résultat attendu :** Statut passé à PENDING_SIGN ; message de succès

### CTR-05 – Signer un contrat (client)

- **Rôle :** Client
- **Préconditions :** Contrat en attente de signature client
- **Étapes :**
  1. Aller sur `/client/contracts/{id}/sign`
  2. Saisir la signature (canvas ou données) et soumettre (`POST /client/contracts/{id}/sign/submit`)
- **Résultat attendu :** Signature client enregistrée ; si les deux parties ont signé, contrat actif et conversation créée si applicable

### CTR-06 – Annuler un contrat (client)

- **Rôle :** Client
- **Préconditions :** Contrat annulable selon les règles métier
- **Étapes :**
  1. Sur la fiche contrat, déclencher l’annulation (`POST /client/contracts/{id}/cancel`) avec éventuelle raison
- **Résultat attendu :** Contrat annulé ; redirection vers la liste

### CTR-07 – Financer un contrat (avance) – Stripe

- **Rôle :** Client
- **Préconditions :** Contrat éligible au financement, Stripe configuré
- **Étapes :**
  1. Cliquer sur « Financer » / « Fund upfront » (`POST /client/contracts/{id}/fund-upfront`)
- **Résultat attendu :** Redirection vers Stripe Checkout ou confirmation du paiement ; statut contrat mis à jour si applicable

---

## 6. Worker (Freelancer) – Offres et contrats

### WK-01 – Lister les contrats (worker)

- **Rôle :** Worker
- **Étapes :**
  1. Aller sur `/worker/contracts`
- **Résultat attendu :** Liste des contrats où le worker est impliqué

### WK-02 – Voir un contrat (worker)

- **Rôle :** Worker
- **Étapes :**
  1. Aller sur `/worker/contracts/{id}`
- **Résultat attendu :** Détail du contrat ; accès uniquement si le worker est bien le freelancer du contrat

### WK-03 – Télécharger le PDF (worker)

- **Rôle :** Worker
- **Étapes :**
  1. Sur la fiche contrat, demander le PDF (`/worker/contracts/{id}/pdf`)
- **Résultat attendu :** PDF du contrat

### WK-04 – Signer un contrat (worker)

- **Rôle :** Worker
- **Préconditions :** Contrat en attente de signature worker
- **Étapes :**
  1. Aller sur `/worker/contracts/{id}/sign`
  2. Saisir la signature et soumettre (`POST /worker/contracts/{id}/sign/submit`)
- **Résultat attendu :** Signature worker enregistrée ; si double signature, contrat actif

### WK-05 – Créer une offre (worker) – depuis l’admin

- **Rôle :** Admin (création d’offre pour un worker) ou Worker selon les routes
- **Étapes :**
  1. Depuis une demande de service (ou sélection de request), créer une offre avec prix, délai, worker
  2. Soumettre (ex. `/admin/offers/new/{serviceRequest}` ou équivalent worker)
- **Résultat attendu :** Offre créée et liée à la demande

### WK-06 – Modifier une offre (worker / admin)

- **Rôle :** Worker ou Admin
- **Étapes :**
  1. Aller sur l’édition de l’offre (`/admin/offers/{id}/edit` ou route worker si existante)
  2. Modifier prix / délai / description et sauvegarder
- **Résultat attendu :** Offre mise à jour

---

## 7. Worker – Profil et certificat

### PRF-01 – Voir son profil

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Aller sur `/profile` ou `/profile/show`
- **Résultat attendu :** Affichage des infos du compte (email, nom, rôle, etc.)

### PRF-02 – Modifier son profil

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Aller sur `/profile/edit`
  2. Modifier nom, prénom, téléphone, etc. et sauvegarder
- **Résultat attendu :** Profil mis à jour ; message de succès

### PRF-03 – Upload photo de profil

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Sur `/profile/picture/upload` (POST), envoyer un fichier image
- **Résultat attendu :** Photo enregistrée et affichée sur le profil

### PRF-04 – Supprimer la photo de profil

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Déclencher « Supprimer la photo » (`POST /profile/picture/remove`)
- **Résultat attendu :** Photo supprimée

### PRF-05 – Enrôlement face (profil)

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Aller sur `/profile/face` ou `/profile/face/enroll` et suivre le flux d’enrôlement
- **Résultat attendu :** Profil face créé ; possibilité de connexion par face

### PRF-06 – Worker : créer / éditer un profil worker (CV)

- **Rôle :** Worker
- **Étapes :**
  1. Aller sur la section Worker Profile (création ou édition)
  2. Remplir titre, bio, catégorie, tarif horaire ; optionnel : upload CV pour extraction IA
- **Résultat attendu :** Profil worker créé ou mis à jour ; si CV uploadé, champs pré-remplis par l’IA (si service actif)

### PRF-07 – Worker : upload certificat

- **Rôle :** Worker
- **Étapes :**
  1. Depuis le profil ou la section certificat, uploader un fichier certificat (PDF/image)
- **Résultat attendu :** Certificat enregistré ; statut « en attente » ou envoyé pour vérification IA

---

## 8. Tickets (support)

### TKT-01 – Créer un ticket

- **Rôle :** Client ou Worker (authentifié)
- **Étapes :**
  1. Aller sur `/ticket/create`
  2. Choisir une catégorie, saisir le sujet et le message
  3. Soumettre
- **Résultat attendu :** Ticket créé ; redirection vers la liste ou la fiche du ticket

### TKT-02 – Lister les tickets

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Aller sur `/ticket/list`
- **Résultat attendu :** Liste des tickets de l’utilisateur (ou tous pour admin)

### TKT-03 – Voir un ticket

- **Rôle :** Utilisateur connecté (ou admin)
- **Étapes :**
  1. Cliquer sur un ticket ou aller sur `/ticket/{id}`
- **Résultat attendu :** Détail du ticket avec messages (sous-tickets)

### TKT-04 – Répondre à un ticket

- **Rôle :** Utilisateur concerné ou admin
- **Étapes :**
  1. Sur la fiche ticket, saisir une réponse et soumettre (`POST /ticket/{id}/reply` ou quick-reply)
- **Résultat attendu :** Nouveau message attaché au ticket ; affiché dans la conversation

### TKT-05 – Noter la satisfaction (ticket)

- **Rôle :** Client / utilisateur ayant ouvert le ticket
- **Préconditions :** Ticket résolu ou fermé
- **Étapes :**
  1. Sur `/ticket/{id}/satisfaction` (POST), envoyer une note (ex. 1–5)
- **Résultat attendu :** Note enregistrée ; message de remerciement

### TKT-06 – Lister les catégories de tickets

- **Rôle :** Utilisateur connecté
- **Étapes :**
  1. Aller sur `/ticket/categories/list` ou équivalent
- **Résultat attendu :** Liste des catégories disponibles pour créer un ticket

---

## 9. Messagerie

### MSG-01 – Lister les conversations

- **Rôle :** Client ou Worker connecté
- **Étapes :**
  1. Aller sur `/messagerie` ou `/messagerie/conversations`
- **Résultat attendu :** Liste des conversations (liées aux contrats signés)

### MSG-02 – Voir les messages d’une conversation

- **Rôle :** Client ou Worker
- **Étapes :**
  1. Sélectionner une conversation ou aller sur `/messagerie/conversation/{id}/messages`
- **Résultat attendu :** Liste des messages de la conversation

### MSG-03 – Envoyer un message

- **Rôle :** Client ou Worker
- **Étapes :**
  1. Dans une conversation, saisir un message et envoyer (`POST /messagerie/conversation/{id}/messages`)
- **Résultat attendu :** Message enregistré et affiché ; l’autre partie peut le voir

### MSG-04 – Supprimer une conversation

- **Rôle :** Client ou Worker (selon règles métier)
- **Étapes :**
  1. Déclencher la suppression (`POST /messagerie/conversation/{id}/delete`)
- **Résultat attendu :** Conversation supprimée (ou archivée) ; plus visible dans la liste

---

## 10. Tableau de bord et navigation

### DASH-01 – Dashboard client

- **Rôle :** Client
- **Étapes :**
  1. Se connecter en tant que client et aller sur `/client/dashboard`
- **Résultat attendu :** Affichage des statistiques client (demandes, offres, contrats, etc.)

### DASH-02 – Dashboard worker

- **Rôle :** Worker
- **Étapes :**
  1. Se connecter en tant que worker et aller sur `/worker/dashboard`
- **Résultat attendu :** Affichage des statistiques worker (offres, contrats, etc.)

### DASH-03 – Dashboard admin

- **Rôle :** Admin
- **Étapes :**
  1. Se connecter en tant qu’admin et aller sur `/admin/dashboard`
- **Résultat attendu :** Tableau de bord admin avec liens vers users, offres, services, contrats, certificats, etc.

### DASH-04 – Changer d’espace (client / freelancer)

- **Rôle :** Utilisateur ayant à la fois ROLE_CLIENT et ROLE_WORKER
- **Étapes :**
  1. Utiliser le lien « Switch state » (`/switch-state?state=client` ou `state=worker`)
- **Résultat attendu :** Redirection vers le dashboard client ou worker selon le state

### DASH-05 – Demande pour devenir freelancer (client)

- **Rôle :** Client
- **Étapes :**
  1. Aller sur `/client/apply-freelancer` et soumettre la demande
- **Résultat attendu :** Demande enregistrée ; rôle worker ajouté après validation admin (selon règles métier)

---

## 11. Admin – Gestion

### ADM-01 – Lister les utilisateurs

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur la section User Management (ex. `/admin/users` ou route équivalente)
- **Résultat attendu :** Liste des utilisateurs avec filtres possibles

### ADM-02 – Voir / éditer un utilisateur

- **Rôle :** Admin
- **Étapes :**
  1. Cliquer sur un utilisateur ; modifier statut, rôle, photo de profil si proposé
- **Résultat attendu :** Modifications enregistrées

### ADM-03 – Lister les catégories worker

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur `/admin/worker-categories` (AdminCategoryController)
- **Résultat attendu :** Liste des catégories ; création, édition, suppression possibles

### ADM-04 – Créer / éditer une catégorie worker

- **Rôle :** Admin
- **Étapes :**
  1. Créer ou éditer une catégorie (nom, description, icône, ordre, tarif)
- **Résultat attendu :** Catégorie enregistrée ; affichée côté client/worker selon les écrans

### ADM-05 – Lister les offres (admin)

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur `/admin/offers`
- **Résultat attendu :** Liste des offres avec filtres

### ADM-06 – Lister / gérer les services (demandes ou modèles)

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur `/admin/services` ; voir, créer, éditer, supprimer des services ou exigences
- **Résultat attendu :** CRUD cohérent avec les messages de succès/erreur

### ADM-07 – Lister les contrats (admin)

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur la section Contracts (ex. `/admin/contracts`)
- **Résultat attendu :** Liste des contrats ; accès aux fiches et actions selon droits

### ADM-08 – Gestion des certificats (worker)

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur `/admin/certificates` ; voir la liste des certificats en attente
  2. Approuver ou rejeter un certificat (`/admin/certificates/{id}/approve`, `/admin/certificates/{id}/reject`)
- **Résultat attendu :** Statut du certificat mis à jour ; worker notifié si applicable

### ADM-09 – Logs d’authentification faciale

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur `/admin/face-logs`
- **Résultat attendu :** Liste des connexions / tentatives par face (utilisateurs, dates, etc.)

### ADM-10 – Catégories de tickets

- **Rôle :** Admin
- **Étapes :**
  1. Aller sur la gestion des catégories de tickets (AdminCategoryTicketController)
- **Résultat attendu :** Liste des catégories ; création, édition, suppression

### ADM-11 – Tickets (admin)

- **Rôle :** Admin
- **Étapes :**
  1. Accéder à la liste des tickets (AdminTicketController) ; ouvrir un ticket, répondre en tant qu’admin
- **Résultat attendu :** Réponses enregistrées avec l’admin comme expéditeur

---

## 12. API (JWT)

### API-01 – Login API (JWT)

- **Rôle :** Visiteur
- **Étapes :**
  1. `POST /api/login` avec body JSON `{"email":"...","password":"..."}`
- **Résultat attendu :** Réponse 200 avec token JWT (ou 401 si invalide)

### API-02 – 2FA verify API

- **Préconditions :** Premier login réussi, 2FA requis
- **Étapes :**
  1. `POST /api/auth/2fa/verify` avec le code TOTP
- **Résultat attendu :** 200 et JWT complet

### API-03 – Face login API

- **Étapes :**
  1. `POST /api/face-login` avec les données d’authentification faciale
- **Résultat attendu :** 200 et JWT si le visage est reconnu ; 401 sinon

### API-04 – Face enroll API (authentifié)

- **Préconditions :** JWT valide
- **Étapes :**
  1. `POST /api/face/enroll` avec les données d’enrôlement
- **Résultat attendu :** 200 et profil face créé/mis à jour

### API-05 – Notifications (dropdown)

- **Préconditions :** JWT valide
- **Étapes :**
  1. `GET /api/notifications/dropdown`
- **Résultat attendu :** Liste des notifications (non lues / récentes)

### API-06 – Marquer une notification comme lue

- **Étapes :**
  1. `POST /api/notifications/{id}/read`
- **Résultat attendu :** Notification marquée comme lue

---

## 13. Paiement (Stripe)

### PAY-01 – Webhook Stripe (simulation)

- **Rôle :** Système (Stripe envoie vers `/stripe/webhook`)
- **Étapes :**
  1. Envoyer un événement de test (ex. `checkout.session.completed`) vers le webhook
- **Résultat attendu :** Réponse 200 ; statut du contrat ou du paiement mis à jour selon la logique métier

### PAY-02 – Paiement upfront (client) – parcours complet

- **Rôle :** Client
- **Préconditions :** Contrat éligible, Stripe configuré
- **Étapes :**
  1. Sur la fiche contrat, cliquer sur Financer ; compléter le paiement sur Stripe Checkout
- **Résultat attendu :** Paiement enregistré ; contrat marqué comme payé (ou selon règles)

---

## 14. IA et intégrations

### AI-01 – Génération de contenu IA (demande de service)

- **Rôle :** Client (ou Admin)
- **Préconditions :** Service IA actif (ex. Flask sur port 5000)
- **Étapes :**
  1. Sur la création/édition de demande, déclencher la génération IA (`POST /ai/generate/{id}` ou équivalent)
- **Résultat attendu :** Champs pré-remplis (ex. titre, exigences) selon la réponse IA

### AI-02 – Score IA sur une demande

- **Rôle :** Client
- **Étapes :**
  1. Sur une demande, demander le score IA (`POST /requests/{id}/ai-score` ou `apply-score`)
- **Résultat attendu :** Score affiché ou appliqué selon la fonctionnalité

### AI-03 – Vérification de certificat (worker)

- **Rôle :** Worker ou Admin
- **Préconditions :** Service certificat IA actif (ex. ai_service sur 8001)
- **Étapes :**
  1. Upload d’un certificat ; lancer la vérification IA
- **Résultat attendu :** Verdict / score IA enregistrés ; affichés sur la fiche certificat

### AI-04 – Insight IA sur un ticket

- **Rôle :** Utilisateur ou Admin
- **Préconditions :** Service ticket support IA actif
- **Étapes :**
  1. Ouvrir un ticket ; déclencher une suggestion ou analyse IA si l’interface le propose
- **Résultat attendu :** Suggestion ou catégorie proposée (selon implémentation)

---

## 15. Sécurité et accès

### SEC-01 – Accès à une route admin sans être admin

- **Rôle :** Client ou Worker (sans ROLE_ADMIN)
- **Étapes :**
  1. Tenter d’accéder à `/admin/dashboard` ou `/admin/users`
- **Résultat attendu :** 403 Forbidden ou redirection vers page d’accès refusé

### SEC-02 – Accès à un contrat d’un autre client

- **Rôle :** Client A
- **Étapes :**
  1. Modifier l’URL pour accéder à `/client/contracts/{id}` d’un contrat appartenant au client B
- **Résultat attendu :** 403 ou 404 ; pas d’affichage des données du contrat B

### SEC-03 – Accès à une offre d’un autre client

- **Rôle :** Client A
- **Étapes :**
  1. Tenter d’accéder à une offre liée à une demande du client B
- **Résultat attendu :** 403 ou 404

### SEC-04 – Utilisateur banni ne peut pas accéder aux espaces protégés

- **Préconditions :** Utilisateur banni
- **Étapes :**
  1. Se connecter (si possible) puis tenter d’accéder à `/client/dashboard` ou `/worker/dashboard`
- **Résultat attendu :** Redirection vers `/banned` ou message d’accès refusé

---

## 16. Synthèse et priorisation

| Domaine | Nombre de scénarios | Priorité suggérée |
|---------|---------------------|--------------------|
| Authentification | 14 | Haute |
| Client – Demandes de service | 7 | Haute |
| Client – Offres | 7 | Haute |
| Client – Contrats | 7 | Haute |
| Worker – Contrats / Offres | 6 | Haute |
| Profil / Certificat | 7 | Moyenne |
| Tickets | 6 | Moyenne |
| Messagerie | 4 | Moyenne |
| Dashboards | 5 | Moyenne |
| Admin | 11 | Haute |
| API JWT / Notifications | 6 | Haute |
| Paiement Stripe | 2 | Haute |
| IA | 4 | Moyenne |
| Sécurité | 4 | Haute |

**Total : ~90 scénarios.**

---

## 17. Comment utiliser ce document

- **Tests manuels :** Suivre les étapes pour chaque scénario et noter Pass / Fail / Bloqué.
- **Tests E2E (Playwright, Cypress, etc.) :** Utiliser les scénarios comme spécifications (préconditions, étapes, assertions).
- **Couverture :** Vérifier que chaque route critique (login, register, offres, contrats, paiement, admin) est couverte par au moins un scénario positif et un scénario d’échec (validation, accès refusé).
- **Données de test :** Prévoir des comptes de test (client, worker, admin) et des données (demandes, offres, contrats en différents statuts) pour exécuter les scénarios de façon reproductible.

Pour les **tests unitaires** des règles métier (validation des entités), voir `docs/TESTS_UNITAIRES_ENTITES.md`.
