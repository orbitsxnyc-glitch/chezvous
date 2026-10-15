"use client";

import { useState } from "react";
import { useAuth } from "@/components/AuthProvider";
import type { SearchParams } from "@/lib/params";
import { createSavedSearch } from "@/lib/engagement";
import { generalError } from "@/lib/forms";
import Link from "next/link";

const CRITERIA = ["transaction", "type", "locality", "agency", "q", "price_min", "price_max", "surface_min", "surface_max", "bedrooms_min", "furnished"];

/** Enregistre les filtres de la recherche courante comme alerte (quotidienne par défaut). */
export function SaveSearchButton({ params }: { params: SearchParams }) {
  const { user, loading } = useAuth();
  const [state, setState] = useState<"idle" | "saved" | "error">("idle");
  const [message, setMessage] = useState("");
  const criteria = Object.fromEntries(Object.entries(params).filter(([k]) => CRITERIA.includes(k)));
  if (loading || Object.keys(criteria).length === 0) return null;

  if (!user) return <p className="text-sm"><Link className="underline" href="/connexion">Connectez-vous</Link> pour être alerté des nouvelles annonces de cette recherche.</p>;

  async function save() {
    setMessage("");
    try {
      await createSavedSearch({ name: Object.values(criteria).join(" · ").slice(0, 80), criteria, frequency: "daily" });
      setState("saved");
    } catch (e) {
      setState("error");
      setMessage(generalError(e));
    }
  }

  return (
    <div className="flex items-center gap-3 text-sm">
      <button type="button" onClick={save} disabled={state === "saved"} className="rounded-lg border border-line-strong px-3 py-1 disabled:opacity-50">
        {state === "saved" ? "✓ Alerte enregistrée" : "🔔 M'alerter des nouvelles annonces"}
      </button>
      {state === "saved" && <Link className="underline" href="/alertes">Gérer mes alertes</Link>}
      {message && <span role="alert" className="text-danger">{message}</span>}
    </div>
  );
}
