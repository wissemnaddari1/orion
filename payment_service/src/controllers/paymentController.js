'use strict';

const getStripe = require('../stripe');
const { getContractById, markUpfrontPaid, calcUpfrontAmountCents } = require('../services/paymentService');

/**
 * POST /api/payments/intent
 * Body: { contractId: number, clientEmail: string }
 *
 * Creates a Stripe PaymentIntent for the upfront portion of a contract.
 * Returns { clientSecret } so the frontend can confirm with Stripe.js.
 */
async function createPaymentIntent(req, res, next) {
  try {
    const stripe = getStripe();
    const { contractId, clientEmail } = req.body;

    const contract = await getContractById(contractId);
    if (!contract) {
      return res.status(404).json({ error: 'Contract not found' });
    }
    if (contract.upfront_paid) {
      return res.status(409).json({ error: 'Upfront payment already completed' });
    }
    if (!['ACTIVE', 'IN_PROGRESS'].includes(contract.status)) {
      return res.status(422).json({ error: `Contract status '${contract.status}' does not allow payment` });
    }

    const amountCents = calcUpfrontAmountCents(contract);
    const currency    = (contract.currency || 'usd').toLowerCase();
    const useFake     = process.env.USE_FAKE_STRIPE === 'true';

    const intentParams = {
      amount:   amountCents,
      currency,
      metadata: {
        contract_id:    String(contractId),
        contract_title: contract.title ?? '',
        client_id:      String(contract.client_id),
        worker_id:      String(contract.worker_id),
      },
    };

    // localstripe does not support receipt_email / description — only add for real Stripe
    if (!useFake) {
      intentParams.receipt_email = clientEmail;
      intentParams.description   = `Upfront payment for contract #${contractId} — ${contract.title ?? ''}`;
    }

    const intent = await stripe.paymentIntents.create(intentParams);

    return res.json({ clientSecret: intent.client_secret });
  } catch (err) {
    next(err);
  }
}

/**
 * GET /api/payments/contract/:contractId
 * Returns upfront payment status for a contract.
 */
async function getContractPaymentStatus(req, res, next) {
  try {
    const contractId = parseInt(req.params.contractId, 10);
    if (!Number.isFinite(contractId)) {
      return res.status(400).json({ error: 'Invalid contractId' });
    }

    const contract = await getContractById(contractId);
    if (!contract) {
      return res.status(404).json({ error: 'Contract not found' });
    }

    const amountCents = calcUpfrontAmountCents(contract);

    return res.json({
      contractId,
      status:          contract.status,
      upfrontPaid:     Boolean(contract.upfront_paid),
      upfrontPaidAt:   contract.upfront_paid_at,
      upfrontPercent:  contract.upfront_percent,
      upfrontAmountCents: amountCents,
      currency:        contract.currency,
    });
  } catch (err) {
    next(err);
  }
}

/**
 * POST /api/payments/webhook
 * Handles Stripe webhook events.
 *
 * In fake mode (USE_FAKE_STRIPE=true):
 *   - Signature verification is skipped.
 *   - The request body is parsed as plain JSON (no raw-buffer needed).
 *
 * In production (USE_FAKE_STRIPE=false):
 *   - STRIPE_WEBHOOK_SECRET must be set.
 *   - The raw request buffer is used to verify the Stripe-Signature header.
 */
async function handleWebhook(req, res, next) {
  const useFake = process.env.USE_FAKE_STRIPE === 'true';

  let event;

  if (useFake) {
    // Fake server sends plain JSON — parse it directly, no signature check.
    try {
      const body = Buffer.isBuffer(req.body) ? JSON.parse(req.body.toString()) : req.body;
      event = body;
      console.log(`[webhook] Fake mode — accepted event '${event.type}' without signature check`);
    } catch (err) {
      return res.status(400).json({ error: `Failed to parse webhook body: ${err.message}` });
    }
  } else {
    const stripe = getStripe();
    const sig    = req.headers['stripe-signature'];
    const secret = process.env.STRIPE_WEBHOOK_SECRET;

    if (!secret) {
      return res.status(500).json({ error: 'STRIPE_WEBHOOK_SECRET is not configured' });
    }

    try {
      event = stripe.webhooks.constructEvent(req.body, sig, secret);
    } catch (err) {
      return res.status(400).json({ error: `Webhook signature invalid: ${err.message}` });
    }
  }

  if (event.type === 'payment_intent.succeeded') {
    const intent     = event.data.object;
    const contractId = parseInt(intent.metadata?.contract_id, 10);

    if (Number.isFinite(contractId)) {
      try {
        await markUpfrontPaid(contractId);
        console.log(`[webhook] Contract #${contractId} marked as upfront paid`);
      } catch (err) {
        // Log but don't reject — Stripe would retry otherwise
        console.error(`[webhook] Failed to mark contract #${contractId} paid:`, err.message);
      }
    }
  }

  res.json({ received: true });
}

module.exports = { createPaymentIntent, getContractPaymentStatus, handleWebhook };
