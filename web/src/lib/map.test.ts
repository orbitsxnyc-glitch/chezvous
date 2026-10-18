import { describe, expect, it } from "vitest";
import { bboxParam, compactPrice, isCluster, mapFilters, type MapItem } from "./map";

describe("carte", () => {
  it("abrège les prix pour les pastilles", () => {
    expect(compactPrice(450000)).toBe("450 k");
    expect(compactPrice(1250000)).toBe("1,25 M");
    expect(compactPrice(2000000)).toBe("2 M");
    expect(compactPrice(800)).toBe("800");
    expect(compactPrice(null)).toBe("—");
  });

  it("formate l'emprise dans l'ordre attendu par l'API", () => {
    expect(bboxParam(-17.52, 14.7, -17.45, 14.78)).toBe("-17.52,14.7,-17.45,14.78");
    expect(bboxParam(-17.523456789, 14.7, -17.45, 14.78)).toBe("-17.52346,14.7,-17.45,14.78");
  });

  it("ne transmet que les filtres connus, jamais le curseur ni la vue", () => {
    const q = mapFilters({ transaction: "rent_long", locality: "yoff", cursor: "abc", vue: "carte", evil: "x", price_max: "500000" });
    expect(Object.fromEntries(q)).toEqual({ transaction: "rent_long", locality: "yoff", price_max: "500000" });
  });

  it("distingue un groupe d'une annonce", () => {
    const cluster: MapItem = { cluster: true, count: 12, lat: 14.7, lon: -17.4, min_price_xof: 100000 };
    const point: MapItem = { id: "a", reference: "CV-1", transaction: "rent_long", price_xof: 1, title: null, lat: 14.7, lon: -17.4, cover: null, locality: null, property_type_label: null };
    expect(isCluster(cluster)).toBe(true);
    expect(isCluster(point)).toBe(false);
  });
});
