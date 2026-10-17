#!/usr/bin/env node
// Test de charge HTTP de la recherche publique. Usage :
//   node infra/scripts/load-test.mjs http://127.0.0.1:8013 [requêtes=1500]
// À lancer contre un serveur LOCAL ou de test, jamais contre la production. Les chiffres d'un poste de développement
// sont indicatifs : ils servent à repérer les régressions, pas à dimensionner l'hébergement.
const base = (process.argv[2] ?? "http://127.0.0.1:8000").replace(/\/$/, "");
const total = Number(process.argv[3] ?? 1500);

const PATHS = [
  "/api/v1/listings",
  "/api/v1/listings?transaction=rent_long&price_max=500000&locality=yoff&bedrooms_min=2",
  "/api/v1/listings?locality=dakar-department&sort=price_asc",
  "/api/v1/listings?q=yoff",
  "/api/v1/listings?type=apartment&sort=price_desc",
  "/api/v1/listings/map?bbox=-17.52,14.70,-17.45,14.78",
  "/api/v1/localities?level=neighborhood",
];

const pct = (sorted, p) => sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))];

async function run(concurrency, count) {
  const latencies = [];
  const statuses = new Map();
  let next = 0;
  const started = performance.now();
  async function worker() {
    while (next < count) {
      const i = next++;
      const url = base + PATHS[i % PATHS.length];
      const t0 = performance.now();
      try {
        const res = await fetch(url, { headers: { Accept: "application/json" } });
        const text = await res.text();
        // Un code 200 ne suffit pas (une erreur PHP peut répondre 200) : le corps doit être du JSON avec une clé « data ».
        let valid = false;
        try {
          valid = Array.isArray(JSON.parse(text).data);
        } catch {
          valid = false;
        }
        const key = res.status === 200 && !valid ? "200 corps invalide" : res.status;
        statuses.set(key, (statuses.get(key) ?? 0) + 1);
      } catch {
        statuses.set("réseau", (statuses.get("réseau") ?? 0) + 1);
      }
      latencies.push(performance.now() - t0);
    }
  }
  await Promise.all(Array.from({ length: concurrency }, worker));
  const seconds = (performance.now() - started) / 1000;
  latencies.sort((a, b) => a - b);
  return { concurrency, count, rps: count / seconds, p50: pct(latencies, 50), p95: pct(latencies, 95), p99: pct(latencies, 99), max: latencies.at(-1), statuses: Object.fromEntries(statuses) };
}

console.log(`Cible : ${base} · ${total} requêtes par palier`);
const rows = [];
for (const c of [1, 10, 25, 50]) {
  const r = await run(c, total);
  rows.push(r);
  const errors = Object.entries(r.statuses).filter(([k]) => k !== "200").map(([k, v]) => `${k}×${v}`).join(" ") || "aucune";
  console.log(`concurrence ${String(c).padStart(2)} : ${r.rps.toFixed(0).padStart(4)} req/s · p50 ${r.p50.toFixed(0)} ms · p95 ${r.p95.toFixed(0)} ms · p99 ${r.p99.toFixed(0)} ms · max ${r.max.toFixed(0)} ms · erreurs : ${errors}`);
}
const bad = rows.filter((r) => Object.keys(r.statuses).some((k) => k !== "200"));
process.exit(bad.length > 0 ? 1 : 0);
