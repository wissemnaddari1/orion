# Local Payment Testing (Fake Stripe Server)

Test contract payments locally without a real Stripe account by running a fake
Stripe server that speaks the Stripe HTTP API.

---

## 1. Start the fake Stripe server

### Option A — localstripe (recommended)

Runs on **port 8420** by default. No Docker required.

```bash
pip install localstripe
localstripe
```

### Option B — stripe-mock (Docker)

Official Stripe mock. Runs on **port 12111**.

```bash
docker run --rm -p 12111:12111 stripe/stripe-mock
```

> If you use stripe-mock, set `STRIPE_API_BASE=http://localhost:12111` in the root `.env`.

---

## 2. Configure the root `.env`

Open `c:\Users\fouad\Desktop\CodeVeins-master\CodeVeins-master\.env` and set:

```dotenv
STRIPE_SECRET_KEY=sk_test_example  # any non-empty value works with localstripe
STRIPE_PUBLISHABLE_KEY=pk_test_example
STRIPE_WEBHOOK_SECRET=whsec_example  # ignored in fake mode

USE_FAKE_STRIPE=true
STRIPE_API_BASE=http://localhost:8420   # match whichever fake server you chose

PAYMENT_SERVICE_PORT=3001
PAYMENT_SERVICE_URL=http://localhost:3001
```

---

## 3. Install dependencies (first run only)

```bash
cd payment_service
npm install
```

---

## 4. Start the payment service

```bash
# development (auto-restarts on file change)
npm run dev

# or plain node
npm start
```

Expected output:

```
[stripe] Using FAKE Stripe server at http://localhost:8420
[payment-service] Listening on port 3001
```

---

## 5. Test the full payment flow

### 5.1 Health check

```bash
curl http://localhost:3001/health
# → {"status":"ok","service":"codeveins-payment","ts":"..."}
```

### 5.2 Create a PaymentIntent

```bash
curl -X POST http://localhost:3001/api/payments/intent \
  -H "Content-Type: application/json" \
  -d '{"contractId": 1, "clientEmail": "client@example.com"}'
```

Response:

```json
{ "clientSecret": "pi_fake_..._secret_..." }
```

### 5.3 Check upfront payment status

```bash
curl http://localhost:3001/api/payments/contract/1
```

### 5.4 Simulate a successful payment (trigger webhook)

Send a `payment_intent.succeeded` event directly to the webhook endpoint.
In fake mode, signature verification is **skipped** so you can POST raw JSON:

```bash
curl -X POST http://localhost:3001/api/payments/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment_intent.succeeded",
    "data": {
      "object": {
        "id": "pi_fake_123",
        "metadata": { "contract_id": "1" }
      }
    }
  }'
```

Expected response:

```json
{ "received": true }
```

The contract row in MySQL will now have `upfront_paid = 1` and
`status = IN_PROGRESS` (if it was previously `ACTIVE`).

---

## 6. Switching back to real Stripe

Set in the root `.env`:

```dotenv
USE_FAKE_STRIPE=false
STRIPE_SECRET_KEY=sk_live_...       # real key from dashboard.stripe.com
STRIPE_WEBHOOK_SECRET=whsec_...    # from the Stripe dashboard webhook settings
```

Then restart the service. The Stripe client will connect to the real API and
full webhook signature verification will be enforced.

---

## Mode comparison

| Scenario | `USE_FAKE_STRIPE` | Stripe key | Signature check |
|---|---|---|---|
| Local dev | `true` | any non-empty | **skipped** |
| Staging/prod | `false` | real `sk_live_*` or `sk_test_*` | **enforced** |
