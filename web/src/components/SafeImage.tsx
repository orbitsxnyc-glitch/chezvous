"use client";

import { useState } from "react";

/** Photo qui ne montre jamais d'icône d'image cassée : si le fichier est introuvable, on affiche un fond neutre avec le libellé. */
export function SafeImage({ src, alt, className = "", fallbackLabel = "Photo indisponible", eager = false }: { src: string; alt: string; className?: string; fallbackLabel?: string; eager?: boolean }) {
  const [failed, setFailed] = useState(false);
  if (failed || !src) {
    return (
      <div role="img" aria-label={alt} className={`flex items-center justify-center bg-sand-deep px-3 text-center text-xs text-muted ${className}`}>
        {fallbackLabel}
      </div>
    );
  }
  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      // Une image déjà en échec avant l'hydratation ne déclenche plus onError : on la détecte à l'attache de l'élément.
      ref={(el) => {
        if (el?.complete && el.naturalWidth === 0) setFailed(true);
      }}
      src={src}
      alt={alt}
      loading={eager ? "eager" : "lazy"}
      onError={() => setFailed(true)}
      className={className}
    />
  );
}
