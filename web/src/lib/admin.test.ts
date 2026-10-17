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
