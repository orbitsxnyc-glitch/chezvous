"use client";

import type { InputHTMLAttributes, ReactNode } from "react";

/** Primitives de formulaire au style du design Figma (jetons dans globals.css). */

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
        className="min-h-12 rounded-lg border border-line-strong bg-white px-3 py-2 text-ink focus:border-primary focus:ring-3 focus:ring-primary/10 focus:outline-none"
        {...input}
      />
      {hint && !error && (
        <p id={`${id}-hint`} className="text-xs text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} role="alert" className="text-sm text-danger">
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
      className="min-h-12 rounded-lg bg-primary px-5 py-2 font-bold text-white hover:bg-primary-hover disabled:opacity-50"
    >
      {pending ? "Veuillez patienter…" : children}
    </button>
  );
}

export function FormMessage({ kind, children }: { kind: "error" | "info"; children: ReactNode }) {
  return (
    <p role={kind === "error" ? "alert" : "status"} className={kind === "error" ? "text-danger" : "text-success"}>
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
