#!/usr/bin/env node
// Captures d'écran et audit d'accessibilité (axe-core, WCAG 2.1 AA, contrastes réels) du site web, dans Chromium.
// Prérequis (hors dépôt, aucun compte) : un dossier avec `playwright` et `@axe-core/playwright` installés
// et `npx playwright install chromium`. Usage :
//   PLAYWRIGHT_DIR=/chemin/dossier node infra/scripts/web-audit.mjs http://localhost:3000 /tmp/web-shots [references...]
import { createRequire } from "node:module";
import { mkdirSync, writeFileSync } from "node:fs";

const base = (process.argv[2] ?? "http://localhost:3000").replace(/\/$/, "");
const out = process.argv[3] ?? "/tmp/web-shots";
const extra = process.argv.slice(4);
const require = createRequire(`${process.env.PLAYWRIGHT_DIR ?? "."}/`);
const { chromium } = require("playwright");
const AxeBuilder = require("@axe-core/playwright").default;

const pages = [
  ["accueil", "/"],
  ["recherche", "/annonces"],
  ["recherche-filtree", "/annonces?transaction=rent_long&locality=yoff"],
  ["seo-quartier", "/a-louer/appartements/yoff"],
  ["connexion", "/connexion"],
  ["inscription", "/inscription"],
  ["offres", "/offres"],
  ["mes-annonces", "/mes-annonces"],
  ["admin", "/admin"],
  ...extra.map((ref) => [`fiche-${ref}`, `/annonces/${ref}`]),
];
const viewports = { bureau: { width: 1280, height: 800 }, mobile: { width: 390, height: 844 } };

mkdirSync(out, { recursive: true });
// CHROMIUM_PATH : navigateur déjà installé (sinon celui que Playwright télécharge).
const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
const report = [];
for (const [vpName, viewport] of Object.entries(viewports)) {
  const context = await browser.newContext({ viewport, locale: "fr-FR", colorScheme: "light" });
  const page = await context.newPage();
  for (const [name, path] of pages) {
    const response = await page.goto(base + path, { waitUntil: "networkidle" }).catch(() => null);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${out}/${vpName}-${name}.png`, fullPage: true });
    const horizontalScroll = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "best-practice"]).analyze();
    report.push({
      vp: vpName, page: name, status: response?.status() ?? 0, horizontalScroll,
      violations: results.violations.map((v) => ({ id: v.id, impact: v.impact, help: v.help, count: v.nodes.length, sample: v.nodes[0]?.target?.join(" ") ?? "", detail: v.nodes[0]?.failureSummary?.split("\n").slice(0, 3).join(" | ") ?? "" })),
    });
  }
  await context.close();
}
await browser.close();
writeFileSync(`${out}/rapport.json`, JSON.stringify(report, null, 2));

let total = 0;
for (const r of report) {
  total += r.violations.length;
  console.log(`${r.vp.padEnd(6)} ${r.page.padEnd(20)} HTTP ${r.status}${r.horizontalScroll ? " · DÉFILEMENT HORIZONTAL" : ""} · ${r.violations.length} violation(s)`);
  for (const v of r.violations) console.log(`    - [${v.impact}] ${v.id} ×${v.count} : ${v.help} (${v.sample})`);
}
console.log(`Total : ${total} violation(s) sur ${report.length} pages`);
process.exit(total > 0 ? 1 : 0);
