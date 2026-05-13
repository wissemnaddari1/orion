# PROPOSED ARCHITECTURE - Service / Negotiation / Contract + AI Matchmaking

## Canonical state machines

### Service lifecycle (ServiceRequest)

States:

- `OPEN`
- `IN_PROGRESS`
- (future) `COMPLETED`
- (future) `CANCELLED`

Transitions:

- `create`: system sets `OPEN`
- `edit`: remains `OPEN`
- `accept offer`: `OPEN -> IN_PROGRESS`
- `complete service`: (future) `IN_PROGRESS -> COMPLETED`
- `cancel service`: (future) `OPEN|IN_PROGRESS -> CANCELLED`

### Negotiation lifecycle

States:

- `OPEN`
- `ACCEPTED`
- `REJECTED`
- (future) `COUNTERED`, `EXPIRED`

Transitions:

- `start negotiation`: `PENDING offer -> NEGOTIATING offer`, negotiation `OPEN`
- `accept`: negotiation `OPEN -> ACCEPTED`, offer `NEGOTIATING -> ACCEPTED`
- `reject`: negotiation `OPEN -> REJECTED`, offer `NEGOTIATING -> REJECTED`
- `abort`: remove negotiation row, offer `NEGOTIATING -> PENDING`

### Contract lifecycle

States:

- `DRAFT`
- `PENDING_SIGN`
- `ACTIVE`
- `IN_PROGRESS`
- `COMPLETED`
- `CANCELLED`
- `DISPUTED`

Transitions:

- `send for signing`: `DRAFT -> PENDING_SIGN`
- `both signatures completed`: `PENDING_SIGN -> ACTIVE`
- `fund upfront` or `first delivery/revision`: `ACTIVE -> IN_PROGRESS`
- `all milestones finalized + funded`: `ACTIVE|IN_PROGRESS -> COMPLETED`
- `cancel`: `DRAFT|PENDING_SIGN|ACTIVE|IN_PROGRESS -> CANCELLED`

## Transition responsibility map (actor/controller/db/effects)

- Service request create
  - Actor: `client` or `admin`
  - Controller: `ServiceRequestController::create`, `ServiceAdminController::create`
  - DB: insert `service_request`
  - Effects: none
- Start matchmaking
  - Actor: `client`/`admin` owner
  - Controller: `ServiceRequestController::startService`
  - DB: create `ai_recommendation` snapshot + `offer` rows
  - Effects: worker notifications
- Offer negotiation start
  - Actor: `client`
  - Controller: `Client\OfferController::negotiate` or AJAX equivalents
  - DB: insert `negotiation`, update `offer.status`
  - Effects: mail + notifications
- Offer acceptance
  - Actor: `client` (or worker acceptance of negotiation now aligned)
  - Controller: `Client\OfferController::accept`, `WorkerController::negotiationAccept`, API variants
  - DB: accepted offer, reject competing offers, service `IN_PROGRESS`, create contract
  - Effects: notifications
- Contract sign
  - Actor: `client`/`worker`
  - Controller: `ClientController::contractSignSubmit`, `WorkerController::contractSignSubmit`
  - DB: signature fields; auto-activate when fully signed
  - Effects: status progression
- Contract funding + delivery + completion
  - Actor: `client`/`worker`/payment system
  - Controller: `ClientController` payment/milestone/complete, `WorkerController::milestoneDeliver`, webhook/payment API
  - DB: funding flags, milestone statuses, released amounts, contract status
  - Effects: payment and process progression

## AI integration points

### Required now

1. **Service discovery recommendations**
   - Entry: service detail and start-service matchmaking flow
   - Service: `AiMatchmakingService::getRecommendationsForService(serviceId, userId, context)`
   - Output: ranked workers with score + human explanations
   - Persistence: `ai_recommendation` snapshots

2. **Before negotiation recommendations**
   - Entry: client offer detail before calling negotiate
   - Service: same `AiMatchmakingService` with context `stage=pre_negotiation`
   - Output shown in `offer_show` UI for decision support

### Optional (feature-flag ready, not mandatory now)

- Negotiation suggestions:
  - suggested price band / delivery windows
- Contract suggestions:
  - scope clauses, acceptance criteria, risk notes

## Data model changes

- New table: `ai_recommendation`
  - `service_request_id` FK
  - `recommended_user_id` FK
  - `requested_by_id` FK nullable
  - `score` float
  - `explanations` json
  - `context` json nullable
  - `created_at` datetime immutable

Rationale: a dedicated table keeps a clean audit trail, avoids coupling recommendation snapshots to mutable `Offer` lifecycle fields, and supports offline analysis/re-ranking without breaking current domain entities.

## Caching strategy

- Cache key: `ai_reco_{serviceId}_{userId}_{contextHash}`
- Backend: Symfony `cache.app`
- TTL: 5 minutes
- Benefit: reduces external matchmaking calls, while keeping recommendation freshness acceptable for service discovery and pre-negotiation screens.

