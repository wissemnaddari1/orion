'use strict';

require('dotenv').config({ path: require('path').resolve(__dirname, '../.env') });

const express = require('express');
const paymentRoutes = require('./src/routes/paymentRoutes');
const { errorHandler } = require('./src/middlewares/errorHandler');

const app = express();
const PORT = process.env.PAYMENT_SERVICE_PORT || 3001;

// Webhook body handling:
//   Production (USE_FAKE_STRIPE=false): raw buffer required for signature verification.
//   Fake mode   (USE_FAKE_STRIPE=true):  plain JSON is fine — no sig check performed.
// express.raw captures the body as a Buffer in both cases; the controller decides
// what to do with it, so we always mount it here regardless of mode.
app.use('/api/payments/webhook', express.raw({ type: 'application/json' }));

app.use(express.json());

app.get('/health', (_req, res) => {
  res.json({ status: 'ok', service: 'codeveins-payment', ts: new Date().toISOString() });
});

app.use('/api/payments', paymentRoutes);

app.use(errorHandler);

app.listen(PORT, () => {
  console.log(`[payment-service] Listening on port ${PORT}`);
});

module.exports = app;
