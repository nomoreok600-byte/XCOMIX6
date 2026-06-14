"use strict";

const mysql = require("mysql2/promise");

/**
 * Shared MySQL connection pool. Configuration is read from the environment so
 * the same code runs locally and inside cPanel's Node.js Application Manager.
 */
const pool = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || "root",
  password: process.env.DB_PASSWORD || "",
  database: process.env.DB_NAME || "xcomix",
  waitForConnections: true,
  connectionLimit: Number(process.env.DB_CONNECTION_LIMIT || 10),
  queueLimit: 0,
  charset: "utf8mb4_unicode_ci",
  namedPlaceholders: true,
});

async function query(sql, params) {
  const [rows] = await pool.execute(sql, params || {});
  return rows;
}

module.exports = { pool, query };
