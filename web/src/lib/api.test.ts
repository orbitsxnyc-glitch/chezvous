// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError, apiFetch } from "./api";

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } });

describe("apiFetch", () => {
  beforeEach(() => {
    document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT";
  });
  afterEach(() => vi.restoreAllMocks());

  it("récupère le cookie CSRF avant une requête POST et envoie l'en-tête X-XSRF-TOKEN", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (url) => {
      if (String(url).endsWith("/sanctum/csrf-cookie")) {
        document.cookie = "XSRF-TOKEN=abc%3D";
        return new Response(null, { status: 204 });
      }
      return json(200, { data: { ok: true } });
    });

    await apiFetch("/auth/login", { method: "POST", body: { phone: "1" } });

    expect(String(fetchMock.mock.calls[0][0])).toContain("/sanctum/csrf-cookie");
    const init = fetchMock.mock.calls[1][1] as RequestInit;
    expect((init.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBe("abc=");
    expect(init.credentials).toBe("include");
  });

  it("n'appelle pas le cookie CSRF pour un GET", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(json(200, { data: {} }));

    await apiFetch("/me");

    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("transforme une erreur de l'API en ApiError avec code et erreurs de champs", async () => {
    document.cookie = "XSRF-TOKEN=t";
    vi.spyOn(globalThis, "fetch").mockResolvedValue(
      json(422, { code: "validation_error", message: "Invalide", errors: { phone: ["Numéro invalide"] } }),
    );

    const error = await apiFetch("/auth/register", { method: "POST", body: {} }).catch((e) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 422, code: "validation_error", errors: { phone: ["Numéro invalide"] } });
  });

  it("signale une panne réseau avec un message clair", async () => {
    vi.spyOn(globalThis, "fetch").mockRejectedValue(new TypeError("fail"));

    await expect(apiFetch("/me")).rejects.toMatchObject({ code: "network_error", status: 0 });
  });

  it("renvoie undefined pour une réponse 204", async () => {
    document.cookie = "XSRF-TOKEN=t";
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 204 }));

    await expect(apiFetch("/me", { method: "DELETE" })).resolves.toBeUndefined();
  });
});

describe("apiFetch (envoi de fichiers)", () => {
  afterEach(() => vi.restoreAllMocks());

  it("n'impose pas de Content-Type JSON à un FormData et ne le sérialise pas", async () => {
    document.cookie = "XSRF-TOKEN=t";
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(json(201, { data: {} }));
    const form = new FormData();
    form.append("method", "wave");

    await apiFetch("/my/subscription-requests/x/payments", { method: "POST", body: form });

    const init = fetchMock.mock.calls[0][1] as RequestInit;
    expect((init.headers as Record<string, string>)["Content-Type"]).toBeUndefined();
    expect(init.body).toBe(form);
  });
});
