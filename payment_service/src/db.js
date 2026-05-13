'use strict';

const mysql = require('mysql2/promise');

/**
 * Parse mysql://user:pass@host:port/dbname from DATABASE_URL.
 * Falls back to explicit env vars so the service can also run standalone.
 */
function buildPoolConfig() {
  const url = process.env.DATABASE_URL;
  if (url) {
    const m = url.match(/^mysql:\/\/([^:]*):([^@]*)@([^:]+):(\d+)\/(.+)$/);
    if (m) {
      return {
        host: m[3],
        port: parseInt(m[4], 10),
        user: m[1] || 'root',
        password: m[2] || undefined,
        database: m[5],
      };
    }
  }
  return {
    host: process.env.DB_HOST || '127.0.0.1',
    port: parseInt(process.env.DB_PORT || '3306', 10),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || undefined,
    database: process.env.DB_NAME || 'orion',
  };
}

const pool = mysql.createPool({
  ...buildPoolConfig(),
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  timezone: '+00:00',
});

module.exports = pool;
