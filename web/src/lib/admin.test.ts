import { describe, expect, it } from "vitest";
import { isAdmin } from "./admin";
import { formatXof } from "./format";

describe("admin", () => {
  it("reconnaît les rôles d'administration", () => {
    expect(isAdmin(["admin"])).toBe(true);
    expect(isAdmin(["super_admin"])).toBe(true);
    expect(isAdmin(["particulier"])).toBe(false);
    expect(isAdmin(undefined)).toBe(false);
  });
  it("formate les montants en francs CFA", () => {
    expect(formatXof(1250000)).toBe("1 250 000 FCFA");
  });
});

import { labelById } from "./admin";

describe("modération", () => {
  it("retrouve le libellé d'un référentiel par identifiant", () => {
    const types = [{ id: 1, label: "Appartement" }, { id: 2, label: "Maison" }];
    const places = [{ id: 7, name: "Yoff" }];
    expect(labelById(types, 2)).toBe("Maison");
    expect(labelById(places, 7)).toBe("Yoff");
    expect(labelById(types, 99)).toBeNull();
    expect(labelById(types, null)).toBeNull();
  });
});

import { parseLimit } from "./adminCatalog";

describe("catalogue", () => {
  it("lit les quotas : vide = à fixer, texte invalide ignoré", () => {
    expect(parseLimit("")).toBeNull();
    expect(parseLimit(" 12 ")).toBe(12);
    expect(parseLimit("0")).toBe(0);
    expect(parseLimit("-1")).toBeUndefined();
    expect(parseLimit("1,5")).toBeUndefined();
  });
});

import { isValidCode, parseCoordinate } from "./adminReferentials";

describe("référentiels", () => {
  it("valide les codes techniques", () => {
    expect(isValidCode("parking_prive")).toBe(true);
    expect(isValidCode("Parking")).toBe(false);
    expect(isValidCode("a")).toBe(false);
    expect(isValidCode("avec espace")).toBe(false);
  });
  it("lit les coordonnées décimales", () => {
    expect(parseCoordinate("14,7167")).toBe(14.7167);
    expect(parseCoordinate("-17.4677")).toBe(-17.4677);
    expect(parseCoordinate("")).toBeUndefined();
    expect(Number.isNaN(parseCoordinate("abc"))).toBe(true);
  });
});

import { placeholders, unknownVariables } from "./admin";

describe("modèles de notification", () => {
  it("repère les variables non autorisées avant l'envoi", () => {
    expect(placeholders("Annonce {listing} : {reason} {listing}")).toEqual(["listing", "reason"]);
    expect(unknownVariables("Bonjour {phone}, annonce {listing}", ["listing"])).toEqual(["phone"]);
    expect(unknownVariables("Sans variable", [])).toEqual([]);
  });
});
