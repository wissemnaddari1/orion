'use strict';

const db = require('../db');

const CONTRACT_STATUS_ACTIVE = 'ACTIVE';
const CONTRACT_STATUS_IN_PROGRESS = 'IN_PROGRESS';

/**
 * Fetch a contract row by id.
 * Returns null if not found.
 */
async function getContractById(contractId) {
  const [rows] = await db.execute(
    `SELECT id, client_id, worker_id, agreed_price, currency,
            upfront_percent, upfront_paid, upfront_paid_at, status, title
     FROM contract
     WHERE id = ?`,
    [contractId]
  );
  return rows[0] ?? null;
}

/**
 * Mirror Contract::markUpfrontPaid() from PHP:
 *   - upfront_paid = true
 *   - upfront_paid_at = now()
 *   - if status == ACTIVE → status = IN_PROGRESS
 */
async function markUpfrontPaid(contractId) {
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

  await db.execute(
    `UPDATE contract
     SET upfront_paid    = 1,
         upfront_paid_at = ?,
         status          = IF(status = ?, ?, status)
     WHERE id = ?`,
    [now, CONTRACT_STATUS_ACTIVE, CONTRACT_STATUS_IN_PROGRESS, contractId]
  );
}

/**
 * Calculate the upfront amount in cents for Stripe.
 * agreed_price is stored as a decimal (e.g. "1500.00"), currency as ISO code.
 */
function calcUpfrontAmountCents(contract) {
  const price = parseFloat(contract.agreed_price);
  const pct   = parseFloat(contract.upfront_percent);
  return Math.round((price * pct) / 100 * 100); // ×100 → cents
}

module.exports = { getContractById, markUpfrontPaid, calcUpfrontAmountCents };
