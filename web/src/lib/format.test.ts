import { describe, expect, it } from "vitest";
import { formatPrice, freshnessLabel, relativeSince } from "./format";

describe("formatPrice", () => {
  it("formate une location mensuelle", () => expect(formatPrice(450000, "rent_long")).toBe("450 000 FCFA / mois"));
  it("formate une vente sans période", () => expect(formatPrice(50000000, "sale")).toBe("50 000 000 FCFA"));
  it("gère l'absence de prix", () => expect(formatPrice(null, "sale")).toBe("Prix non communiqué"));
});

describe("freshnessLabel", () => {
  const now = new Date("2026-10-16T12:00:00Z");
  it("date la confirmation d'un bien disponible", () =>
    expect(freshnessLabel("available", "2026-10-16T09:42:00Z", now)).toBe("Disponible · confirmé il y a 2 h"));
  it("demande de reconfirmer", () =>
    expect(freshnessLabel("to_reconfirm", "2026-10-14T12:00:00Z", now)).toContain("À reconfirmer"));
  it("avertit quand l'information est ancienne", () =>
    expect(freshnessLabel("stale", null, now)).toBe("Information ancienne : disponibilité à vérifier"));
});

describe("relativeSince", () => {
  const now = new Date("2026-10-16T12:00:00Z");
  it("moins d'une heure", () => expect(relativeSince(new Date("2026-10-16T11:30:00Z"), now)).toBe("il y a moins d'une heure"));
  it("jours au pluriel", () => expect(relativeSince(new Date("2026-10-13T12:00:00Z"), now)).toBe("il y a 3 jours"));
  it("un jour au singulier", () => expect(relativeSince(new Date("2026-10-15T12:00:00Z"), now)).toBe("il y a 1 jour"));
});
