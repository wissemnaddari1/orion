'use strict';

/**
 * Middleware: ensure required fields are present in req.body.
 * @param {string[]} fields
 */
function validateBody(fields) {
  return (req, res, next) => {
    const missing = fields.filter(f => req.body[f] === undefined || req.body[f] === null || req.body[f] === '');
    if (missing.length > 0) {
      return res.status(400).json({ error: `Missing required fields: ${missing.join(', ')}` });
    }
    next();
  };
}

module.exports = { validateBody };
