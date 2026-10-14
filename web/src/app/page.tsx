import Link from "next/link";

export default function Home() {
  return (
    <main className="mx-auto flex w-full max-w-md flex-col gap-4 p-6">
      <h1 className="text-3xl font-semibold">ChezVous</h1>
      <p>Trouvez. Vérifiez. Visitez.</p>
      <nav className="flex flex-col gap-2 underline">
        <Link href="/inscription">Créer un compte</Link>
        <Link href="/connexion">Se connecter</Link>
      </nav>
    </main>
  );
}
