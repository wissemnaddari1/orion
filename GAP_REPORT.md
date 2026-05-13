# GAP REPORT - CodeVeins / Orion

## Current scenario summary

The current implementation models the business flow with:

- `ServiceRequest` (client demand posting)
- `Offer` (worker response + matchmaking candidate artifact)
- `Negotiation` (single offer-level negotiation snapshot)
- `Contract` + `Milestone` (execution and delivery/funding workflow)
- `Notification` (workflow side-effects and user prompts)

There is **already matchmaking logic** (`MatchmakingClient`, `ServiceRequestMatchmakingService`) but it is not exposed as a first-class reusable domain component and has no persisted recommendation history separate from `Offer`.

## Current state map (audited)

### Entities + fields + relations

- `src/Entity/ServiceRequest.php`
  - Key fields: `title`, `description`, `budget_min`, `budget_max`, `status`, `duration`, `level`, `createdAt`
  - Relations: `ManyToOne client(User)`, `ManyToOne category(WorkerCategory)`, `OneToMany requirements(ServiceRequirement)`, `OneToMany offers(Offer)`
- `src/Entity/ServiceRequirement.php`
  - Key fields: `title`, `details`, `requirement_type`, `answer_format`, `options_json`, `is_mandatory`, `priority_level`
  - Relation: `ManyToOne service(ServiceRequest)`
- `src/Entity/Offer.php`
  - Key fields: `price`, `estimatedTimeDays`, `status`, `matchScore`, `proposedBudget`, `proposedDeadline`, `priorityLevel`, `isUrgent`
  - Relations: `ManyToOne serviceRequest`, `ManyToOne worker(User)`, `ManyToOne client(User, nullable)`, `OneToOne negotiation`
- `src/Entity/Negotiation.php`
  - Key fields: `status`, `counterPrice`, `timelineDays`, `scopeDetails`, `expiresAt`, `lastActionAt`
  - Relations: `OneToOne offer`, `ManyToOne openedBy(User)`, `ManyToOne targetUser(User)`
- `src/Entity/Contract.php`
  - Key fields: `status`, signing fields, funding fields (`upfrontPaid`, `releasedAmount`), `riskScore`, `riskLevel`
  - Relations: `ManyToOne client(User)`, `ManyToOne worker(User)`, `OneToMany milestones(Milestone)`
- `src/Entity/Milestone.php`
  - Key fields: `title`, `dueDate`, `status`, `amount`, `deliveredAt`, `completedAt`
  - Relation: `ManyToOne contract`
- `src/Entity/Notification.php`
  - Key fields: `type`, `title`, `body`, `payload`, `isRead`
  - Relation: `ManyToOne user`

### Status fields + allowed values

- Service request status (`ServiceRequest.status`)
  - Storage: `VARCHAR(50)`, default `OPEN`
  - Used values found: `OPEN`, `IN_PROGRESS` (worker listings also treat `COMPLETED/CLOSED/CANCELLED` as closed filters)
- Offer status (`Offer.status`)
  - Constants: `PENDING`, `ACCEPTED`, `DECLINED`, `REJECTED`, `NEGOTIATING`
  - Template usages additionally reference `EXPIRED`
- Negotiation status (`Negotiation.status`)
  - Commented/intended values: `OPEN`, `COUNTERED`, `ACCEPTED`, `REJECTED`, `EXPIRED`
  - Runtime usage mainly: `OPEN`, `ACCEPTED`, `REJECTED`
- Contract status (`Contract.status`)
  - Constants: `DRAFT`, `PENDING_SIGN`, `ACTIVE`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `DISPUTED`
- Milestone status (`Milestone.status`)
  - Constants: `PENDING`, `IN_PROGRESS`, `DELIVERED`, `REVISION_REQUESTED`, `COMPLETED`, `CANCELLED`

### Validators / security rules observed

- Role-gated controllers (`IsGranted`/`denyAccessUnlessGranted`) for client/worker/admin paths.
- Ownership checks implemented in controller actions (client owns service request, worker owns offer/contract, etc.).
- CSRF checks in many form POST actions (not all API endpoints by design).
- Manual validation logic in controllers (titles, budgets, duration, milestone constraints, dates, amounts).
- Entity-level assertions heavily used in `User`; workflow entities rely mainly on controller validation.

## Flow consistency check

### Canonical state machine (Service)

- `OPEN -> IN_PROGRESS` on offer acceptance.
- `OPEN -> OPEN` on edit/admin-edit (forced reset).
- `IN_PROGRESS -> COMPLETED/CANCELLED` currently not implemented in controllers (gap).

### Canonical state machine (Negotiation)

- `OPEN -> ACCEPTED` (client accepts negotiated offer or worker accepts)
- `OPEN -> REJECTED` (worker/client rejects)
- `OPEN -> (removed)` via abort negotiation (record deleted, offer goes back to `PENDING`)
- `COUNTERED/EXPIRED` declared conceptually but not implemented as first-class transitions.

### Canonical state machine (Contract)

- `DRAFT -> PENDING_SIGN` (`client_contract_send_sign`)
- `PENDING_SIGN -> ACTIVE` when both signatures are collected (`signByClient/signByWorker`)
- `ACTIVE -> IN_PROGRESS` when upfront is funded or first milestone delivered/revised
- `ACTIVE/IN_PROGRESS -> COMPLETED` with milestone/funding guards
- `DRAFT/PENDING_SIGN/ACTIVE/IN_PROGRESS -> CANCELLED`

### Inconsistencies detected

1. **Duplicate route space for offer action APIs**
   - Both `src/Controller/OfferActionApiController.php` and `src/Controller/Api/OfferActionController.php` expose `POST /offers/{id}/accept|decline|negotiate`.
2. **Negotiation acceptance divergence**
   - Worker-side negotiation acceptance did not mirror client-side acceptance side-effects (service status update, contract generation, rejecting competing offers).
3. **Service lifecycle incomplete**
   - Service requests move to `IN_PROGRESS` but no controller transitions to `COMPLETED/CANCELLED`.
4. **Template/backend status mismatch**
   - Offer templates use `EXPIRED`, but offer constants and transitions do not implement this state.
5. **Negotiation model mismatch**
   - `Negotiation` is one-to-one with `Offer`, yet repository/controller semantics sometimes imply "latest negotiation history".
6. **Data/value mismatch**
   - Service level values are mixed-case (`Entry`, `Intermediate`, ...) while templates checked uppercase values.
7. **Dashboard contract status bug**
   - Client dashboard counted contracts with lowercase `active` instead of domain constants.
8. **AI matchmaking persistence gap**
   - Existing AI matching creates `Offer` rows directly but does not store recommendation snapshots/explanations as a first-class model.

## Proposed minimal changes (implemented)

- Added first-class AI service and persistence:
  - `src/Service/AiMatchmakingService.php`
  - `src/Entity/AiRecommendation.php`
  - `src/Repository/AiRecommendationRepository.php`
  - `migrations/Version20260303110000.php`
- Kept existing flow while integrating AI touchpoints:
  - Service discovery recommendations in `ServiceRequestController` + `templates/service_request/show.html.twig`
  - Pre-negotiation recommendations in `Client/OfferController` + `templates/pages/client/offer_show.html.twig`
- Canonicalized conflicts:
  - Moved one API controller route prefix to `/api/offers` to remove collision.
  - Worker negotiation acceptance now applies the same core side-effects as client acceptance.
- Minor consistency fixes:
  - Client dashboard active contracts now uses `Contract::STATUS_ACTIVE/IN_PROGRESS`.
  - Service level badge comparison normalized with `|upper`.

