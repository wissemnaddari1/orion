# AI Contract Generator

FastAPI microservice that generates structured freelance contracts using a trained ML risk model and deterministic rule-based templates (no LLM).

## What it is

This service receives project and offer attributes, predicts contract risk, and returns:

- `generatedContract`: full legal-style contract text
- `riskScore`: numerical risk score
- `riskLevel`: `LOW`, `MEDIUM`, or `HIGH`

## What it can do

- Load a trained model from `contract_risk_model.pkl`
- Compute risk level from structured request features
- Apply risk-aware legal policies:
  - `LOW`: standard protective clauses
  - `MEDIUM`: stricter payment and delay clauses
  - `HIGH`: milestone-enforced payments and stronger penalties
- Generate deterministic contract sections:
  - Scope of Work
  - Deliverables
  - Payment Terms
  - Delivery Conditions
  - Late Penalty Clause
  - Dispute Resolution
  - Termination Clause

## Requirements

- Python `3.11`
- Dependencies installed from `requirements.txt`
- Model file present at:
  - `ai_contract_generator/contract_risk_model.pkl`

## How to launch (Windows / local)

Run all commands from workspace root.

1. Install dependencies:

```bash
py -3.11 -m pip install -r ai_contract_generator/requirements.txt
```

2. Start the API:

```bash
py -3.11 -m uvicorn ai_contract_generator.api:app --host 127.0.0.1 --port 8004 --reload
```

Keep this terminal running while Symfony is creating contracts.

3. Verify health:

```bash
curl http://127.0.0.1:8004/health
```

Expected result:

```json
{"status":"ok"}
```

## API endpoints

### `GET /health`

Returns service health.

Response:

```json
{"status":"ok"}
```

### `POST /generate-contract`

Generates the contract text and risk output.

Request:

```json
{
  "serviceTitle": "Website Redesign",
  "serviceDescription": "Design and develop a responsive website with CMS integration.",
  "requirements": "Figma source files, SEO baseline, 5 core pages, admin training",
  "price": 2500,
  "deliveryDays": 21,
  "deliveryMode": "ONLINE",
  "clientRating": 4.6,
  "freelancerRating": 4.9,
  "negotiationCount": 1,
  "numberOfMilestones": 3
}
```

Response:

```json
{
  "generatedContract": "...full contract text...",
  "riskScore": 0.2714,
  "riskLevel": "LOW"
}
```

## Risk feature inputs

The risk pipeline uses these derived/base features:

- `price`
- `deliveryDays`
- `deliveryMode`
- `clientRating`
- `freelancerRating`
- `negotiationCount`
- `numberOfMilestones`
- `titleLength`
- `descriptionLength`
- `requirementsLength`
- `ratingGap`
- `pricePerDay`

## How risk level is decided (plain text)

The service does not use a fixed hardcoded rule like `if price > X then HIGH`.
It uses a trained ML classifier (logistic regression) to compute a `riskScore` between `0.0` and `1.0`.

In simple terms:

- Higher score => more risky contract profile
- Lower score => safer contract profile

Then the score is mapped to labels:

- `0.00` to `0.33` => `LOW`
- `0.34` to `0.66` => `MEDIUM`
- `0.67` to `1.00` => `HIGH`

Signals that usually increase risk include:

- high price relative to delivery timeline (`pricePerDay`)
- very tight deadlines for expensive work
- high client/freelancer rating gap
- more negotiation rounds (more uncertainty)
- weak project breakdown (milestones)

Signals that usually decrease risk include:

- realistic price vs timeline
- stronger and closer ratings
- clearer project scope/requirements
- better milestone structure

So `HIGH` means: based on training data, this contract looks similar to profiles that were historically riskier.

## Quick test

### Windows PowerShell

```powershell
$body = @{
  serviceTitle = 'Website Redesign'
  serviceDescription = 'Design and develop a responsive website with CMS integration.'
  requirements = 'Figma source files, SEO baseline, 5 core pages, admin training'
  price = 2500
  deliveryDays = 21
  deliveryMode = 'ONLINE'
  clientRating = 4.6
  freelancerRating = 4.9
  negotiationCount = 1
  numberOfMilestones = 3
} | ConvertTo-Json

Invoke-RestMethod -Method Post -Uri 'http://127.0.0.1:8004/generate-contract' -ContentType 'application/json' -Body $body
```

### Bash / Git Bash

```bash
curl -X POST "http://127.0.0.1:8004/generate-contract" \
  -H "Content-Type: application/json" \
  -d '{"serviceTitle":"Website Redesign","serviceDescription":"Design and develop a responsive website with CMS integration.","requirements":"Figma source files, SEO baseline, 5 core pages, admin training","price":2500,"deliveryDays":21,"deliveryMode":"ONLINE","clientRating":4.6,"freelancerRating":4.9,"negotiationCount":1,"numberOfMilestones":3}'
```

## Symfony integration

- Backend URL config in `.env`:
  - `CONTRACT_GENERATOR_API_URL=http://127.0.0.1:8004`
- HTTP client integration:
  - `src/Service/ContractGeneratorClient.php`
- Contract creation logic:
  - `src/Service/ContractFromOfferService.php`

## Failure behavior

- If model file is missing or scoring fails, this API returns HTTP `500`.
- In current backend flow, contract generation is AI-only; if this service is down, contract creation from accepted offer fails (no fallback text).

## Troubleshooting

- Port `8004` in use:
  - Stop the other process or run on another port and update `CONTRACT_GENERATOR_API_URL`.
- Health endpoint failing:
  - Re-check Python version, dependency install, and `uvicorn` command.
- Model load error:
  - Verify `ai_contract_generator/contract_risk_model.pkl` exists and is readable.
