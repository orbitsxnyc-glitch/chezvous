"use client";

import { useEffect, useMemo, useState } from "react";
import { FormMessage } from "@/components/ui";
import { CHANNEL_LABELS, fetchTemplates, saveTemplate, unknownVariables, type TemplateRow } from "@/lib/admin";
import { ApiError } from "@/lib/api";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");
const input = "w-full rounded-lg border border-line-strong bg-white px-3 py-2 text-sm";
const STUBBED = ["sms", "whatsapp", "push"];

function Editor({ row, onSaved, onError }: { row: TemplateRow; onSaved: (v: number) => void; onError: (m: string) => void }) {
  const [subject, setSubject] = useState(row.subject ?? "");
  const [body, setBody] = useState(row.body);
  const [busy, setBusy] = useState(false);
  const bad = unknownVariables(`${subject} ${body}`, row.variables);

  return (
    <form
      className="mt-3 flex flex-col gap-3 rounded-md bg-sand p-3"
      onSubmit={async (e) => {
        e.preventDefault();
        setBusy(true);
        try {
          const res = await saveTemplate(row.type, row.channel, { ...(row.channel === "email" ? { subject } : {}), body });
          onSaved(res.data.version);
        } catch (err) { onError(msg(err)); }
        setBusy(false);
      }}
    >
      {row.channel === "email" && (
        <label className="flex flex-col gap-1 text-xs font-bold">Objet
          <input className={input} value={subject} maxLength={200} onChange={(e) => setSubject(e.target.value)} />
        </label>
      )}
      <label className="flex flex-col gap-1 text-xs font-bold">Message
        <textarea className={input} rows={4} maxLength={2000} value={body} onChange={(e) => setBody(e.target.value)} />
      </label>
      <p className="text-xs text-muted">
        Variables autorisées : {row.variables.length === 0 ? "aucune" : row.variables.map((v) => `{${v}}`).join(", ")}. Aucune donnée personnelle dans un modèle.
      </p>
      {bad.length > 0 && <p role="alert" className="text-xs font-bold text-danger">Variable(s) non autorisée(s) : {bad.map((v) => `{${v}}`).join(", ")}</p>}
      <button type="submit" disabled={busy || bad.length > 0 || body.trim().length < 3} className="min-h-10 self-start rounded-lg bg-primary px-4 text-sm font-bold text-white disabled:opacity-50">
        Enregistrer une nouvelle version
      </button>
    </form>
  );
}

export default function TemplatesView() {
  const [rows, setRows] = useState<TemplateRow[] | null>(null);
  const [channel, setChannel] = useState("email");
  const [search, setSearch] = useState("");
  const [open, setOpen] = useState<string | null>(null);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [version, setVersion] = useState(0);

  useEffect(() => {
    let cancelled = false;
    fetchTemplates().then((r) => !cancelled && setRows(r.data)).catch((e) => !cancelled && setError(msg(e)));
    return () => { cancelled = true; };
  }, [version]);

  const shown = useMemo(
    () => (rows ?? []).filter((r) => r.channel === channel && (search === "" || r.type.includes(search.toLowerCase()) || r.body.toLowerCase().includes(search.toLowerCase()))),
    [rows, channel, search],
  );

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Modèles de messages</h1>
      <p className="text-sm text-muted">Chaque modification crée une nouvelle version ; les anciennes sont conservées et l&apos;enregistrement est journalisé.</p>
      <div className="flex flex-wrap gap-2">
        <select aria-label="Canal" className="min-h-10 rounded-lg border border-line-strong bg-white px-3 text-sm" value={channel} onChange={(e) => { setChannel(e.target.value); setOpen(null); }}>
          {Object.entries(CHANNEL_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <input aria-label="Rechercher" placeholder="Rechercher un type ou un texte" className="min-h-10 min-w-64 rounded-lg border border-line-strong bg-white px-3 text-sm" value={search} onChange={(e) => setSearch(e.target.value)} />
      </div>
      {STUBBED.includes(channel) && <FormMessage kind="info">Ce canal n&apos;envoie rien pour l&apos;instant (fournisseur non choisi) : les modèles sont prêts pour plus tard.</FormMessage>}
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {notice && <FormMessage kind="info">{notice}</FormMessage>}
      {rows === null && !error && <p>Chargement…</p>}
      <ul className="flex flex-col gap-2">
        {shown.map((r) => {
          const id = `${r.type}/${r.channel}`;
          return (
            <li key={id} className="rounded-lg border border-line bg-white p-3">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-extrabold">{r.type} <span className="font-normal text-muted">· version {r.version}</span></p>
                  <p className="mt-1 text-sm text-muted">{r.subject ? `${r.subject} — ` : ""}{r.body}</p>
                </div>
                <button className="shrink-0 text-sm font-bold underline" onClick={() => setOpen(open === id ? null : id)}>{open === id ? "Fermer" : "Modifier"}</button>
              </div>
              {open === id && (
                <Editor key={`${id}-${r.version}`} row={r} onError={(m) => { setError(m); setNotice(""); }} onSaved={(v) => { setNotice(`Version ${v} enregistrée pour ${r.type}.`); setError(""); setOpen(null); setVersion((x) => x + 1); }} />
              )}
            </li>
          );
        })}
      </ul>
      {rows !== null && shown.length === 0 && <p>Aucun modèle pour ce filtre.</p>}
    </section>
  );
}
