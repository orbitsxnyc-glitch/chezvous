import type { MetadataRoute } from "next";

const SITE = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";

export default function robots(): MetadataRoute.Robots {
  return {
    // Les pages de compte n'ont aucun intérêt pour les moteurs de recherche.
    rules: { userAgent: "*", allow: "/", disallow: ["/profil", "/connexion", "/inscription", "/verification", "/mot-de-passe-oublie", "/agence", "/invitation", "/demandes", "/boite", "/favoris", "/alertes", "/notifications", "/abonnement"] },
    sitemap: `${SITE}/sitemap.xml`,
  };
}
