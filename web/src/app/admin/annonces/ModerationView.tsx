"use client";

import { useEffect, useState } from "react";
import { FormMessage } from "@/components/ui";
import {
  approveListing, fetchModerationQueue, fetchRejectionReasons, labelById, rejectListing,
  type ModerationListing, type RejectionReason,
} from "@/lib/admin";
import { ApiError } from "@/lib/api";
import { formatPrice } from "@/lib/format";
import { fetchNeighborhoods, fetchPropertyTypes, type Neighborhood, type PropertyType } from "@/lib/myListings";

const msg = (e: unknown) => (e instanceof ApiError ? e.message : "Erreur.");

function Card({
  listing, types, places, reasons, onDone, onError,
}: {
  listing: ModerationListing;
  types: PropertyType[];
  places: Neighborhood[];
  reasons: RejectionReason[];
  onDone: (id: string) => void;
  onError: (m: string) => void;
}) {
  const [rejecting, setRejecting] = useState(false);
  const [reason, setReason] = useState("");
  const [comment, setComment] = useState("");
  const [busy, setBusy] = useState(false);
  const p = listing.property;
  const images = p.images.filter((i) => i.status === "ready");

  async function run(action: () => Promise<unknown>) {
    setBusy(true);
    try {
      await action();
      onDone(listing.id);
    } catch (e) {
      // 409 : l'annonce a déjà été traitée par un autre modérateur, on la retire de la file.
      if (e instanceof ApiError && (e.status === 409 || e.status === 422)) onDone(listing.id);
      onError(msg(e));
      setBusy(false);
    }
  }

  const facts = [
    labelById(types, p.type_id), labelById(places, p.locality_id),
    p.surface_m2 ? `${p.surface_m2} m²` : null, p.bedrooms !== null ? `${p.bedrooms} ch.` : null, p.furnished ? "Meublé" : null,
  ].filter(Boolean).join(" · ");

  return (
    <li className="flex flex-col gap-3 rounded-lg border border-line bg-white p-4">
      <div>
        <p className="text-xs text-muted">{listing.reference}</p>
        <h2 className="text-lg font-extrabold">{listing.title ?? "Sans titre"}</h2>
        <p className="font-bold text-primary">{formatPrice(listing.price_xof, listing.transaction)}</p>
        <p className="text-sm text-muted">{facts}</p>
      </div>
      {images.length > 0 ? (
        <ul className="flex gap-2 overflow-x-auto">
          {images.map((i) => (
            // eslint-disable-next-line @next/next/no-img-element
            <li key={i.id}><img src={i.urls.medium ?? i.urls.thumb} alt="" className="h-28 w-40 rounded-md object-cover" /></li>
          ))}
        </ul>
      ) : <p className="text-sm text-danger">Aucune photo prête.</p>}
      {listing.description && <p className="max-h-40 overflow-auto text-sm whitespace-pre-line">{listing.description}</p>}
      <p className="text-xs text-muted">Adresse (privée) : {p.address ?? "non renseignée"}</p>

      {rejecting ? (
        <div className="flex flex-col gap-2 rounded-md bg-sand p-3">
          <label className="flex flex-col gap-1 text-sm font-bold">
            Motif du refus
            <select className="min-h-11 rounded-lg border border-line-strong bg-white px-3 font-normal" value={reason} onChange={(e) => setReason(e.target.value)}>
              <option value="">Choisir…</option>
              {reasons.map((r) => <option key={r.code} value={r.code}>{r.label}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm font-bold">
            Message à l&apos;annonceur (facultatif)
            <textarea className="rounded-lg border border-line-strong bg-white px-3 py-2 font-normal" rows={3} maxLength={1000} value={comment} onChange={(e) => setComment(e.target.value)} />
          </label>
          <div className="flex gap-2">
            <button disabled={!reason || busy} className="rounded-lg bg-danger px-4 py-2 text-sm font-bold text-white disabled:opacity-50" onClick={() => run(() => rejectListing(listing.id, reason, comment))}>
              Confirmer le refus
            </button>
            <button className="px-3 text-sm underline" onClick={() => setRejecting(false)}>Annuler</button>
          </div>
        </div>
      ) : (
        <div className="flex gap-2">
          <button disabled={busy} className="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-hover disabled:opacity-50" onClick={() => run(() => approveListing(listing.id))}>
            Approuver et publier
          </button>
          <button disabled={busy} className="rounded-lg border border-line-strong px-4 py-2 text-sm font-bold" onClick={() => setRejecting(true)}>Refuser</button>
        </div>
      )}
    </li>
  );
}

export default function ModerationView() {
  const [items, setItems] = useState<ModerationListing[] | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [types, setTypes] = useState<PropertyType[]>([]);
  const [places, setPlaces] = useState<Neighborhood[]>([]);
  const [reasons, setReasons] = useState<RejectionReason[]>([]);
  const [error, setError] = useState("");

  useEffect(() => {
    let cancelled = false;
    fetchModerationQueue()
      .then((r) => { if (!cancelled) { setItems(r.data); setCursor(r.meta.next_cursor); } })
      .catch((e) => !cancelled && setError(msg(e)));
    fetchRejectionReasons().then((r) => !cancelled && setReasons(r.data)).catch(() => undefined);
    fetchPropertyTypes().then((r) => !cancelled && setTypes(r.data)).catch(() => undefined);
    fetchNeighborhoods().then((r) => !cancelled && setPlaces(r.data)).catch(() => undefined);
    return () => { cancelled = true; };
  }, []);

  async function more(next: string) {
    try {
      const r = await fetchModerationQueue(next);
      setItems((prev) => [...(prev ?? []), ...r.data]);
      setCursor(r.meta.next_cursor);
    } catch (e) {
      setError(msg(e));
    }
  }

  return (
    <section className="flex flex-col gap-4">
      <h1 className="text-2xl font-extrabold">Annonces à modérer</h1>
      <p className="text-sm text-muted">Les plus anciennes d&apos;abord. Chaque décision est enregistrée dans le journal d&apos;audit.</p>
      {error && <FormMessage kind="error">{error}</FormMessage>}
      {items === null && !error && <p>Chargement…</p>}
      {items?.length === 0 && <p>Aucune annonce en attente.</p>}
      <ul className="flex flex-col gap-4">
        {items?.map((l) => (
          <Card key={l.id} listing={l} types={types} places={places} reasons={reasons} onDone={(id) => setItems((prev) => (prev ?? []).filter((x) => x.id !== id))} onError={setError} />
        ))}
      </ul>
      {cursor && <button className="self-start underline" onClick={() => more(cursor)}>Voir plus</button>}
    </section>
  );
}
