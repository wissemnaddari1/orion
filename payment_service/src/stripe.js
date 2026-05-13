'use strict';

const Stripe = require('stripe');

let _stripe = null;

/**
 * Returns the Stripe singleton.
 *
 * Supports two modes controlled by environment variables:
 *
 *   USE_FAKE_STRIPE=true  → connects to a local fake Stripe server
 *                           (localstripe on :8420 or stripe-mock on :12111)
 *                           using STRIPE_API_BASE as the host.
 *
 *   USE_FAKE_STRIPE=false → connects to the real Stripe API (default).
 *
 * The singleton is created lazily so the service starts up cleanly even
 * when Stripe keys are not yet configured (health / DB routes still work).
 */
function getStripe() {
  if (_stripe) return _stripe;

  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) {
    throw new Error('STRIPE_SECRET_KEY is not set — add it to the root .env file');
  }

  const useFake = process.env.USE_FAKE_STRIPE === 'true';

  if (useFake) {
    const apiBase = process.env.STRIPE_API_BASE || 'http://localhost:8420';
    const url     = new URL(apiBase);

    _stripe = new Stripe(key, {
      apiVersion: '2023-10-16',
      host:       url.hostname,
      port:       parseInt(url.port, 10) || (url.protocol === 'https:' ? 443 : 80),
      protocol:   url.protocol.replace(':', ''), // 'http' or 'https'
    });

    console.log(`[stripe] Using FAKE Stripe server at ${apiBase}`);
  } else {
    _stripe = new Stripe(key, { apiVersion: '2023-10-16' });
    console.log('[stripe] Using REAL Stripe API');
  }

  return _stripe;
}

module.exports = getStripe;
