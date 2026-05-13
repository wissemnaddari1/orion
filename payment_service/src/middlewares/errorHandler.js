'use strict';

/**
 * Central Express error handler.
 * Stripe errors, DB errors, and unhandled errors all route here.
 */
function errorHandler(err, _req, res, _next) { // eslint-disable-line no-unused-vars
  console.error('[payment-service] Error:', err.message);

  // Stripe library errors
  if (err.type && err.type.startsWith('Stripe')) {
    const status = err.statusCode || 402;
    return res.status(status).json({ error: err.message, code: err.code });
  }

  // Generic server error — do NOT leak stack traces to clients
  const status = err.status || err.statusCode || 500;
  return res.status(status).json({ error: err.message || 'Internal server error' });
}

module.exports = { errorHandler };
