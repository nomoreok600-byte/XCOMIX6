"use strict";

const express = require("express");
const settings = require("../lib/settings");

const router = express.Router();
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

// Public site configuration consumed by the static frontend: the enabled ad
// slots' HTML and the announcement banner (only when toggled on in /admin).
router.get("/config", wrap(async (_req, res) => {
  res.json(await settings.publicConfig());
}));

module.exports = router;
