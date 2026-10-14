import "server-only";

const BASE = process.env.API_URL ?? process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

/**
 * Lecture publique de l'API depuis le serveur Next.js (aucun cookie, aucune donnée de l'utilisateur).
 * Renvoie null pour un 404 (annonce inexistante ou non publique) ; lève une erreur pour les autres échecs,
 * afin qu'une panne de l'API ne soit jamais mise en cache comme une page vide.
 */
export async function publicGet<T>(path: string, params: Record<string, string> = {}): Promise<T | null> {
  const url = new URL(`${BASE}/api/v1${path}`);
  for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);

  const res = await fetch(url, { headers: { Accept: "application/json" } });
  if (res.status === 404) return null;
  if (!res.ok) throw new Error(`API ${res.status} sur ${path}`);
  return (await res.json()) as T;
}
