"use client";

import type { InputHTMLAttributes, ReactNode } from "react";

/** Composants volontairement minimalistes : le design final viendra de Figma. */

export function Field({
  label,
  error,
  hint,
  ...input
}: { label: string; error?: string; hint?: string } & InputHTMLAttributes<HTMLInputElement>) {
  const id = input.id ?? input.name;
  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={id} className="text-sm font-medium">
        {label}
      </label>
      <input
        id={id}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}
        className="rounded border border-zinc-400 px-3 py-2"
        {...input}
      />
      {hint && !error && (
        <p id={`${id}-hint`} className="text-xs text-zinc-600">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}

export function SubmitButton({ pending, children }: { pending: boolean; children: ReactNode }) {
  return (
    <button
      type="submit"
      disabled={pending}
      className="rounded bg-zinc-900 px-4 py-2 text-white disabled:opacity-50"
    >
      {pending ? "Veuillez patienter…" : children}
    </button>
  );
}

export function FormMessage({ kind, children }: { kind: "error" | "info"; children: ReactNode }) {
  return (
    <p role={kind === "error" ? "alert" : "status"} className={kind === "error" ? "text-red-700" : "text-green-800"}>
      {children}
    </p>
  );
}

export function Page({ title, children }: { title: string; children: ReactNode }) {
  return (
    <main className="mx-auto flex w-full max-w-md flex-col gap-4 p-6">
      <h1 className="text-2xl font-semibold">{title}</h1>
      {children}
    </main>
  );
}
