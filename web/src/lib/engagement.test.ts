import { describe, expect, it } from "vitest";
import { describeNotification, localToIso, notificationHref, type AppNotification } from "./engagement";

const n = (type: string, data: Record<string, unknown> = {}): AppNotification => ({ id: "1", type, data, read: false, created_at: "2026-10-18T10:00:00Z" });

describe("describeNotification", () => {
  it("décrit un rappel de visite avec l'échéance", () => expect(describeNotification(n("visit.reminder", { hours_before: 24, listing: "CV-2026-000001" }))).toBe("Rappel : visite dans 24 h (CV-2026-000001)"));
  it("décrit une alerte de recherche", () => expect(describeNotification(n("search.alert", { count: 3, name: "Yoff" }))).toBe("3 nouvelle(s) annonce(s) pour « Yoff »"));
  it("reste neutre pour un type inconnu", () => expect(describeNotification(n("autre.chose"))).toBe("Notification"));
});

describe("notificationHref", () => {
  it("renvoie vers la demande", () => expect(notificationHref(n("inquiry.new", { inquiry: "abc" }))).toBe("/demandes/abc"));
  it("renvoie vers l'annonce à défaut", () => expect(notificationHref(n("favorite.price_drop", { listing: "CV-2026-000002" }))).toBe("/annonces/CV-2026-000002"));
  it("n'invente pas de lien", () => expect(notificationHref(n("x"))).toBeNull());
});

describe("localToIso", () => {
  it("convertit en ISO UTC", () => expect(localToIso("2026-10-20T10:00")).toMatch(/^2026-10-20T\d{2}:00:00\.000Z$/));
});
