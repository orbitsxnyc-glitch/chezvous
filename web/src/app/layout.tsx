import type { Metadata } from "next";
import localFont from "next/font/local";
import { Suspense } from "react";
import { AuthProvider } from "@/components/AuthProvider";
import { Logo, SiteFooter, SiteHeader } from "@/components/SiteChrome";
import "./globals.css";

// Police embarquée (licence OFL) : aucun téléchargement à la compilation ni à l'exécution.
const manrope = localFont({ src: "./fonts/Manrope.ttf", variable: "--font-manrope", weight: "200 800", display: "swap" });

export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000"),
  title: "ChezVous",
  description: "La plateforme de confiance pour trouver, vérifier et visiter un logement au Sénégal.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr" className={`${manrope.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <AuthProvider>
          {/* L'en-tête lit l'URL : il doit être sous Suspense ; le repli montre au moins le logo. */}
          <Suspense
            fallback={
              <header className="border-b border-line bg-white">
                <div className="mx-auto flex min-h-[68px] w-full max-w-6xl items-center px-5">
                  <Logo />
                </div>
              </header>
            }
          >
            <SiteHeader />
          </Suspense>
          <div className="flex-1">{children}</div>
          <Suspense fallback={null}>
            <SiteFooter />
          </Suspense>
        </AuthProvider>
      </body>
    </html>
  );
}
