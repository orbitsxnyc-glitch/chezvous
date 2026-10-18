import Link from "next/link";
import { SafeImage } from "@/components/SafeImage";
import { formatPrice } from "@/lib/format";
import type { Pricing, PublicUnit } from "@/lib/stays";

const nf = new Intl.NumberFormat("fr-FR");
const money = (n: number) => `${nf.format(n).replace(/ | /g, " ")} FCFA`;

/** Carte d'une unité de séjour : prix par nuit, et total détaillé quand des dates sont choisies. */
export function StayCard({ unit, query = "" }: { unit: PublicUnit; query?: string }) {
  const photo = unit.property.photos[0];
  const image = photo?.medium ?? photo?.thumb ?? "";
  const pricing = unit.pricing as (Pricing & { total_xof?: number }) | undefined;
  const total = pricing && "total_xof" in pricing ? pricing.total_xof : undefined;
  const facts = [unit.property.type, unit.property.bedrooms ? `${unit.property.bedrooms} ch.` : null, unit.property.bathrooms ? `${unit.property.bathrooms} sdb` : null, `${unit.capacity_guests} voyageur${unit.capacity_guests > 1 ? "s" : ""}`].filter(Boolean).join(" · ");
  return (
    <article className="overflow-hidden rounded-xl border border-line bg-white shadow-card transition hover:-translate-y-0.5 hover:shadow-panel">
      <Link href={`/sejours/${unit.id}${query}`} className="flex flex-col">
        <div className="relative aspect-[4/3] overflow-hidden bg-sand-deep">
          <SafeImage src={image} alt={unit.name} className="size-full object-cover" fallbackLabel="Pas de photo" />
          {unit.property.photos.length > 0 && (
            <span className="absolute right-2.5 bottom-2.5 rounded-md bg-black/65 px-2 py-1 text-[0.7rem] font-semibold text-white">{unit.property.photos.length} photo{unit.property.photos.length > 1 ? "s" : ""}</span>
          )}
        </div>
        <div className="flex flex-col gap-1 p-4">
          {unit.property.locality && <p className="text-xs font-semibold text-muted">{unit.property.locality.name}, Dakar</p>}
          <h2 className="text-base leading-snug font-bold">{unit.name}</h2>
          <p className="text-xs text-muted">{facts}</p>
          <p className="mt-2 text-base font-extrabold text-primary">
            {unit.rate ? formatPrice(unit.rate.from_nightly_xof, "rent_furnished_short") : "Tarif à venir"}
          </p>
          {total !== undefined && <p className="text-sm font-bold">Total {money(total)}</p>}
        </div>
      </Link>
    </article>
  );
}

export { money };
