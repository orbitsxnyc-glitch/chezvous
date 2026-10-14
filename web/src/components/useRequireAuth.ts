"use client";

import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { useAuth } from "./AuthProvider";

/** Redirige vers la connexion si l'utilisateur n'est pas connecté. Les droits restent vérifiés par l'API. */
export function useRequireAuth() {
  const router = useRouter();
  const auth = useAuth();
  useEffect(() => {
    if (!auth.loading && !auth.user) router.replace("/connexion");
  }, [auth.loading, auth.user, router]);
  return auth;
}
