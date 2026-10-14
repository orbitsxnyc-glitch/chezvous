import { describe, expect, it } from "vitest";
import { cleanParams } from "./params";
import { seoPath, seoTitle, typeBySlug } from "./seo";

describe("seo", () => {
  it("construit un titre « ville × type × transaction »", () =>
    expect(seoTitle("rent_long", "Appartements", "Sacré-Cœur")).toBe("Appartements à louer à Sacré-Cœur"));
  it("construit l'URL", () => expect(seoPath("sale", "maisons-villas", "yoff")).toBe("/a-vendre/maisons-villas/yoff"));
  it("retrouve un type par son slug et refuse l'inconnu", () => {
    expect(typeBySlug("appartements")?.code).toBe("apartment");
    expect(typeBySlug("chateaux")).toBeNull();
  });
});

describe("cleanParams", () => {
  it("ne garde que les paramètres connus et non vides", () =>
    expect(cleanParams({ q: " yoff ", sort: "", evil: "x", price_max: "500000" })).toEqual({ q: "yoff", price_max: "500000" }));
  it("prend la première valeur d'un paramètre répété", () => expect(cleanParams({ type: ["house", "land"] })).toEqual({ type: "house" }));
  it("tronque les valeurs démesurées", () => expect(cleanParams({ q: "a".repeat(500) }).q).toHaveLength(100));
});
