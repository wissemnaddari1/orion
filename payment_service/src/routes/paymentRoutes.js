'use strict';

const { Router } = require('express');
const { createPaymentIntent, getContractPaymentStatus, handleWebhook } = require('../controllers/paymentController');
const { validateBody } = require('../middlewares/validateRequest');

const router = Router();

// Webhook must use raw body — registered in server.js before express.json()
router.post('/webhook', handleWebhook);

router.post(
  '/intent',
  validateBody(['contractId', 'clientEmail']),
  createPaymentIntent
);

router.get('/contract/:contractId', getContractPaymentStatus);

module.exports = router;
