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
