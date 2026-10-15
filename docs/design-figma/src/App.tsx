import { useState, type ReactNode } from "react";

type Screen =
  | "home"
  | "results"
  | "detail"
  | "contact"
  | "messages"
  | "publish"
  | "account"
  | "agency"
  | "pricing"
  | "admin"
  | "system";

type IconName =
  | "search"
  | "pin"
  | "heart"
  | "user"
  | "message"
  | "shield"
  | "check"
  | "arrow"
  | "sliders"
  | "home"
  | "bell"
  | "plus"
  | "calendar"
  | "chevron"
  | "eye"
  | "clock"
  | "building"
  | "close"
  | "menu"
  | "sun";

const photos = {
  salon:
    "https://images.unsplash.com/photo-1665249934445-1de680641f50?auto=format&fit=crop&w=1200&q=78",
  bureau:
    "https://images.unsplash.com/photo-1613575831056-0acd5da8f085?auto=format&fit=crop&w=1200&q=78",
  vert:
    "https://images.unsplash.com/photo-1738168246881-40f35f8aba0a?auto=format&fit=crop&w=1200&q=78",
  maison:
    "https://images.unsplash.com/photo-1586700641182-0f1024626df6?auto=format&fit=crop&w=1200&q=78",
};

function Icon({ name, size = 20 }: { name: IconName; size?: number }) {
  const paths: Record<IconName, ReactNode> = {
    search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
    pin: <><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></>,
    heart: <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z" />,
    user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
    message: <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />,
    shield: <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />,
    check: <path d="m5 12 4 4L19 6" />,
    arrow: <><path d="M5 12h14" /><path d="m13 6 6 6-6 6" /></>,
    sliders: <><path d="M4 7h16M4 17h16" /><circle cx="9" cy="7" r="2" /><circle cx="15" cy="17" r="2" /></>,
    home: <><path d="m3 11 9-8 9 8" /><path d="M5 10v11h14V10M9 21v-7h6v7" /></>,
    bell: <><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" /><path d="M10 21h4" /></>,
    plus: <><path d="M12 5v14M5 12h14" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M16 3v4M8 3v4M3 10h18" /></>,
    chevron: <path d="m9 18 6-6-6-6" />,
    eye: <><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="2.5" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    building: <><path d="M4 21V4h11v17M15 9h5v12M8 8h3M8 12h3M8 16h3M2 21h20" /></>,
    close: <><path d="m6 6 12 12M18 6 6 18" /></>,
    menu: <><path d="M4 7h16M4 12h16M4 17h16" /></>,
    sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4" /></>,
  };
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {paths[name]}
    </svg>
  );
}

function Text({
  as: Tag = "div",
  className = "",
  children,
}: {
  as?: keyof JSX.IntrinsicElements;
  className?: string;
  children: ReactNode;
}) {
  return <Tag className={className}>{children}</Tag>;
}

function Button({
  children,
  variant = "primary",
  icon,
  onClick,
  className = "",
  disabled = false,
}: {
  children: ReactNode;
  variant?: "primary" | "secondary" | "ghost" | "terracotta" | "danger";
  icon?: IconName;
  onClick?: () => void;
  className?: string;
  disabled?: boolean;
}) {
  return (
    <button className={`btn btn-${variant} ${className}`} onClick={onClick} disabled={disabled}>
      {icon && <Icon name={icon} size={18} />}
      {children}
    </button>
  );
}

function Field({
  label,
  value,
  icon,
  placeholder,
}: {
  label?: string;
  value?: string;
  icon?: IconName;
  placeholder?: string;
}) {
  return (
    <label className="field-wrap">
      {label && <Text className="field-label">{label}</Text>}
      <span className="field">
        {icon && <Icon name={icon} size={18} />}
        <input defaultValue={value} placeholder={placeholder} />
      </span>
    </label>
  );
}

function Badge({
  children,
  tone = "neutral",
  icon,
}: {
  children: ReactNode;
  tone?: "success" | "warning" | "neutral" | "info" | "accent";
  icon?: IconName;
}) {
  return <span className={`badge badge-${tone}`}>{icon && <Icon name={icon} size={14} />}{children}</span>;
}

function Logo({ onClick }: { onClick: () => void }) {
  return (
    <button className="brand" onClick={onClick} aria-label="Retour à l’accueil">
      <span className="brand-mark"><img src="/assets/chezvous-logo.png" alt="" /></span>
      <span className="brand-name">Chez<span>Vous</span></span>
      <span className="brand-beta">Bêta</span>
    </button>
  );
}

const screens: { label: string; value: Screen }[] = [
  { label: "Accueil", value: "home" },
  { label: "Recherche", value: "results" },
  { label: "Fiche d’annonce", value: "detail" },
  { label: "Messages & visites", value: "messages" },
  { label: "Déposer une annonce", value: "publish" },
  { label: "Compte & favoris", value: "account" },
  { label: "Page agence", value: "agency" },
  { label: "Offres & tarifs", value: "pricing" },
  { label: "Administration", value: "admin" },
  { label: "Design system", value: "system" },
];

function Header({ screen, go }: { screen: Screen; go: (screen: Screen) => void }) {
  const [open, setOpen] = useState(false);
  return (
    <header className="header">
      <div className="header-inner">
        <Logo onClick={() => go("home")} />
        <nav className="desktop-nav" aria-label="Navigation principale">
          <button className={screen === "results" ? "nav-active" : ""} onClick={() => go("results")}>Rechercher</button>
          <button onClick={() => go("pricing")}>Offres et tarifs</button>
          <button onClick={() => go("messages")}>Messages</button>
        </nav>
        <div className="header-actions">
          <button className="icon-btn desktop-only" aria-label="Notifications"><Icon name="bell" /></button>
          <Button variant="secondary" icon="plus" className="desktop-only" onClick={() => go("publish")}>Déposer une annonce</Button>
          <button className="avatar desktop-only" onClick={() => go("account")}>AM</button>
          <button className="icon-btn mobile-only" onClick={() => setOpen(!open)} aria-label="Ouvrir le menu"><Icon name={open ? "close" : "menu"} /></button>
        </div>
      </div>
      {open && (
        <div className="mobile-menu">
          {screens.map((item) => (
            <button key={item.value} onClick={() => { go(item.value); setOpen(false); }}>{item.label}<Icon name="chevron" /></button>
          ))}
        </div>
      )}
    </header>
  );
}

function Freshness({ state = "available" }: { state?: "available" | "reserved" | "reconfirm" | "old" }) {
  const values = {
    available: { tone: "success" as const, title: "Disponible", detail: "confirmé il y a 5 h" },
    reserved: { tone: "warning" as const, title: "Réservé", detail: "confirmé il y a 2 jours" },
    reconfirm: { tone: "warning" as const, title: "À reconfirmer", detail: "dernière confirmation il y a 3 jours" },
    old: { tone: "neutral" as const, title: "Information ancienne", detail: "disponibilité à vérifier" },
  };
  const item = values[state];
  return <Badge tone={item.tone} icon="clock"><strong>{item.title}</strong><span className="badge-detail">· {item.detail}</span></Badge>;
}

type Listing = {
  image: string;
  area: string;
  title: string;
  price: string;
  details: string;
  featured?: boolean;
  state?: "available" | "reserved" | "reconfirm" | "old";
};

const listings: Listing[] = [
  { image: photos.salon, area: "Almadies · Dakar", title: "Appartement lumineux avec balcon", price: "450 000 FCFA / mois", details: "3 chambres · 142 m² · Non meublé", featured: true },
  { image: photos.vert, area: "Mermoz · Dakar", title: "Appartement calme proche de la corniche", price: "375 000 FCFA / mois", details: "2 chambres · 96 m² · Meublé", state: "reconfirm" },
  { image: photos.bureau, area: "Yoff · Dakar", title: "Studio équipé dans une résidence", price: "225 000 FCFA / mois", details: "1 chambre · 48 m² · Meublé", state: "reserved" },
  { image: photos.maison, area: "Ngor · Dakar", title: "Maison de ville avec cour", price: "650 000 FCFA / mois", details: "4 chambres · 190 m² · Non meublé", state: "old" },
];

function ListingCard({ item, go, horizontal = false }: { item: Listing; go: (screen: Screen) => void; horizontal?: boolean }) {
  return (
    <article className={`listing-card ${horizontal ? "listing-horizontal" : ""}`} onClick={() => go("detail")}>
      <div className="listing-media">
        <img src={item.image} alt={item.title} loading="lazy" />
        {item.featured && <span className="sponsored">Mise en avant par son annonceur</span>}
        <button className="favorite" aria-label="Ajouter aux favoris" onClick={(e) => e.stopPropagation()}><Icon name="heart" /></button>
      </div>
      <div className="listing-body">
        <Freshness state={item.state} />
        <Text className="listing-area">{item.area}</Text>
        <Text as="h3" className="listing-title">{item.title}</Text>
        <Text className="listing-details">{item.details}</Text>
        <Text className="listing-price">{item.price}</Text>
      </div>
    </article>
  );
}

function SearchPanel({ go }: { go: (screen: Screen) => void }) {
  const [mode, setMode] = useState("Location");
  return (
    <div className="search-panel">
      <div className="segmented">
        {["Location", "Vente"].map((item) => <button key={item} className={mode === item ? "active" : ""} onClick={() => setMode(item)}>{item}</button>)}
      </div>
      <div className="search-grid">
        <Field label="Où cherchez-vous ?" icon="pin" value="Dakar" />
        <Field label="Quartier" value="Almadies, Ngor, Yoff…" />
        <Field label="Budget maximum" value="500 000 FCFA" />
        <Button icon="search" onClick={() => go("results")}>Rechercher</Button>
      </div>
      <Text className="search-note"><Icon name="shield" size={16} /> Des annonces contrôlées et régulièrement reconfirmées.</Text>
    </div>
  );
}

function HomeScreen({ go }: { go: (screen: Screen) => void }) {
  return (
    <>
      <section className="hero">
        <div className="hero-glow" />
        <div className="container hero-inner">
          <Badge tone="accent">Location résidentielle à Dakar</Badge>
          <Text as="h1">Un logement fiable.<br /><span>Une décision sereine.</span></Text>
          <Text className="hero-copy">Découvrez des biens réellement disponibles, avec des informations vérifiées et datées.</Text>
          <SearchPanel go={go} />
          <Text className="hero-caption">623 biens disponibles et reconfirmés cette semaine</Text>
        </div>
      </section>

      <section className="section container">
        <div className="section-heading">
          <div>
            <Text className="eyebrow">Sélection du moment</Text>
            <Text as="h2">Des logements qui méritent votre attention</Text>
          </div>
          <Button variant="ghost" onClick={() => go("results")}>Voir toutes les annonces <Icon name="arrow" /></Button>
        </div>
        <div className="listing-grid">
          {listings.slice(0, 3).map((item) => <ListingCard key={item.title} item={item} go={go} />)}
        </div>
      </section>

      <section className="trust-section">
        <div className="container">
          <div className="section-heading">
            <div>
              <Text className="eyebrow">Pourquoi ChezVous</Text>
              <Text as="h2">Moins d’annonces. Plus de certitudes.</Text>
            </div>
            <Text className="section-intro">Chaque information importante est expliquée simplement. Vous savez ce qui a été contrôlé, et quand.</Text>
          </div>
          <div className="trust-grid">
            <div className="trust-card signature">
              <span className="trust-icon"><Icon name="clock" /></span>
              <Text as="h3">La fraîcheur, toujours datée</Text>
              <Text>La disponibilité de chaque bien est confirmée régulièrement. Pas de pression, seulement une information claire.</Text>
              <Freshness />
            </div>
            <div className="trust-card signature">
              <span className="trust-icon"><Icon name="shield" /></span>
              <Text as="h3">Le Passeport du Bien</Text>
              <Text>Des contrôles explicites, sans note opaque ni promesse excessive.</Text>
              <div className="mini-checks"><span><Icon name="check" /> Téléphone vérifié</span><span><Icon name="check" /> Localisation vérifiée</span></div>
            </div>
            <div className="trust-card">
              <span className="trust-icon"><Icon name="message" /></span>
              <Text as="h3">Un contact protégé</Text>
              <Text>Le numéro de l’annonceur reste privé. Tous les échanges passent par la messagerie ChezVous.</Text>
              <Button variant="ghost" onClick={() => go("messages")}>Découvrir la messagerie <Icon name="arrow" /></Button>
            </div>
          </div>
        </div>
      </section>

      <section className="section container">
        <Text className="eyebrow">Explorer Dakar</Text>
        <Text as="h2">Quartiers populaires</Text>
        <div className="districts">
          {[
            ["Almadies", "82 biens", photos.salon],
            ["Mermoz", "64 biens", photos.vert],
            ["Yoff", "57 biens", photos.maison],
            ["Ngor", "41 biens", photos.bureau],
          ].map(([name, count, image]) => (
            <button key={name} className="district" onClick={() => go("results")}>
              <img src={image} alt="" />
              <span><strong>{name}</strong><small>{count}</small></span>
            </button>
          ))}
        </div>
      </section>
    </>
  );
}

function ResultsScreen({ go }: { go: (screen: Screen) => void }) {
  const [filters, setFilters] = useState(false);
  return (
    <main className="page container">
      <div className="breadcrumbs">Accueil <Icon name="chevron" size={14} /> Location <Icon name="chevron" size={14} /> Dakar</div>
      <div className="results-top">
        <div><Text as="h1">Locations à Dakar</Text><Text>124 biens disponibles, vérifiés et datés</Text></div>
        <Button variant="secondary" icon="bell">Créer une alerte</Button>
      </div>
      <div className="filter-bar">
        <button className="filter-main" onClick={() => setFilters(!filters)}><Icon name="sliders" /> Filtres <span>2</span></button>
        {["Location", "Dakar", "2+ chambres", "≤ 500 000 FCFA", "Meublé"].map((label, index) => <button className={index < 2 ? "filter-active" : ""} key={label}>{label}<Icon name="chevron" size={14} /></button>)}
        <button className="sort">Trier : Pertinence <Icon name="chevron" size={14} /></button>
      </div>
      <div className="results-layout">
        {filters && (
          <aside className="filters-panel">
            <div className="panel-title"><Text as="h3">Affiner la recherche</Text><button onClick={() => setFilters(false)}><Icon name="close" /></button></div>
            <Field label="Budget minimum" placeholder="Ex. 150 000" />
            <Field label="Budget maximum" value="500 000" />
            <Text className="field-label">Type de bien</Text>
            <div className="check-list">{["Appartement", "Maison", "Studio", "Chambre"].map((x) => <label key={x}><input type="checkbox" defaultChecked={x === "Appartement"} /><span>{x}</span></label>)}</div>
            <Button>Appliquer les filtres</Button>
          </aside>
        )}
        <div className="results-list">
          {listings.map((item) => <ListingCard key={item.title} item={item} go={go} horizontal />)}
          <Button variant="secondary" className="load-more">Voir plus d’annonces</Button>
        </div>
        <aside className="map-placeholder">
          <span><Icon name="pin" /></span>
          <Text as="h3">La carte arrive bientôt</Text>
          <Text>La recherche par zone sera disponible dans une prochaine version.</Text>
        </aside>
      </div>
    </main>
  );
}

function Passport() {
  const checks = [
    ["Téléphone vérifié", "12 juin 2025"],
    ["Identité vérifiée", "12 juin 2025"],
    ["Professionnel vérifié", "08 mai 2025"],
    ["Localisation vérifiée", "14 juin 2025"],
    ["Document contrôlé", "14 juin 2025"],
    ["Visite terrain effectuée", "Non effectuée"],
  ];
  return (
    <section className="passport">
      <div className="passport-head">
        <span><Icon name="shield" /></span>
        <div><Text className="eyebrow">Passeport du Bien</Text><Text as="h2">DK-ALM-002458</Text></div>
      </div>
      <Text className="passport-intro">Voici les contrôles réalisés sur cette annonce. Ils sont présentés sans note globale.</Text>
      <div className="passport-checks">
        {checks.map(([label, date]) => <div className={date === "Non effectuée" ? "unchecked" : ""} key={label}><span><Icon name={date === "Non effectuée" ? "close" : "check"} /></span><strong>{label}</strong><small>{date}</small></div>)}
      </div>
      <div className="legal-note"><Icon name="shield" size={18} /><span>Un contrôle de document ne garantit pas la propriété juridique du bien.</span></div>
    </section>
  );
}

function ContactModal({ close, go }: { close: () => void; go: (screen: Screen) => void }) {
  return (
    <div className="modal-layer" role="dialog" aria-modal="true">
      <div className="modal">
        <div className="modal-head"><div><Text className="eyebrow">Contacter l’annonceur</Text><Text as="h2">Votre message à Teranga Immo</Text></div><button className="icon-btn" onClick={close}><Icon name="close" /></button></div>
        <div className="contact-summary"><img src={photos.salon} alt="" /><div><strong>Appartement lumineux avec balcon</strong><span>450 000 FCFA / mois</span></div></div>
        <div className="info-banner"><Icon name="shield" /><span>Votre numéro et celui de l’annonceur restent privés. Les échanges passent par ChezVous.</span></div>
        <label className="field-wrap"><Text className="field-label">Votre message</Text><textarea defaultValue="Bonjour, ce logement est-il toujours disponible ? Je souhaiterais organiser une visite. Merci." /></label>
        <div className="modal-actions"><Button variant="ghost" onClick={close}>Annuler</Button><Button icon="message" onClick={() => go("messages")}>Envoyer le message</Button></div>
      </div>
    </div>
  );
}

function DetailScreen({ go }: { go: (screen: Screen) => void }) {
  const [contact, setContact] = useState(false);
  return (
    <main className="page detail-page">
      <div className="container">
        <div className="breadcrumbs">Accueil <Icon name="chevron" size={14} /> Dakar <Icon name="chevron" size={14} /> Almadies</div>
        <div className="gallery">
          <img className="gallery-main" src={photos.salon} alt="Salon lumineux de l’appartement" />
          <img src={photos.bureau} alt="Coin bureau" />
          <img src={photos.vert} alt="Second salon" />
          <button><Icon name="eye" /> Voir les 12 photos</button>
        </div>
        <div className="detail-layout">
          <div className="detail-content">
            <Freshness />
            <div className="detail-title-row">
              <div><Text className="listing-area">Almadies · Dakar</Text><Text as="h1">Appartement lumineux avec balcon</Text></div>
              <button className="icon-btn"><Icon name="heart" /></button>
            </div>
            <div className="detail-price"><strong>450 000 FCFA <span>/ mois</span></strong><Badge tone="info">Prix négociable</Badge></div>
            <div className="key-facts">{[["3", "Chambres"], ["2", "Salles d’eau"], ["142 m²", "Surface"], ["3e", "Étage"]].map(([value, label]) => <div key={label}><strong>{value}</strong><span>{label}</span></div>)}</div>
            <section className="content-section">
              <Text as="h2">À propos de ce logement</Text>
              <Text>Appartement traversant et lumineux dans une résidence calme des Almadies. Le séjour s’ouvre sur un balcon orienté ouest. Cuisine équipée, groupe électrogène et gardiennage inclus.</Text>
              <div className="amenities">{["Climatisation", "Balcon", "Parking", "Gardien", "Groupe électrogène", "Réservoir d’eau"].map((x) => <span key={x}><Icon name="check" />{x}</span>)}</div>
            </section>
            <section className="fees content-section">
              <Text className="eyebrow">Transparence</Text>
              <Text as="h2">Frais à l’entrée</Text>
              <div className="fee-list">
                {[["Loyer mensuel", "450 000 FCFA"], ["Caution", "2 mois"], ["Avance", "1 mois"], ["Frais d’agence", "1 mois"], ["Autres frais", "Aucun renseigné"]].map(([a, b]) => <div key={a}><span>{a}</span><strong>{b}</strong></div>)}
              </div>
              <div className="warning-note">Montants renseignés par l’annonceur, à confirmer avant tout paiement. Aucun total n’est calculé par ChezVous.</div>
            </section>
            <Passport />
          </div>
          <aside className="contact-card">
            <Text className="eyebrow">Annonceur</Text>
            <div className="agency-line"><span className="agency-avatar">TI</span><div><Text as="h3">Teranga Immo</Text><Badge tone="info" icon="check">Agence vérifiée</Badge></div></div>
            <Text className="contact-explain">Le téléphone de l’annonceur n’est jamais affiché. Échangez en sécurité dans la messagerie.</Text>
            <Button icon="message" onClick={() => setContact(true)}>Contacter l’annonceur</Button>
            <Button variant="secondary" icon="calendar" onClick={() => setContact(true)}>Proposer une visite</Button>
            <button className="report">Signaler cette annonce</button>
          </aside>
        </div>
        <section className="section related">
          <div className="section-heading"><Text as="h2">Annonces similaires</Text><Button variant="ghost" onClick={() => go("results")}>Voir plus <Icon name="arrow" /></Button></div>
          <div className="listing-grid">{listings.slice(1, 4).map((item) => <ListingCard key={item.title} item={item} go={go} />)}</div>
        </section>
      </div>
      {contact && <ContactModal close={() => setContact(false)} go={go} />}
    </main>
  );
}

function MessagesScreen() {
  const [visit, setVisit] = useState(false);
  return (
    <main className="page container messages-page">
      <div className="page-heading"><div><Text className="eyebrow">Votre espace</Text><Text as="h1">Messages</Text></div><Badge tone="success">Tous les messages sont protégés</Badge></div>
      <div className="messages-shell">
        <aside className="thread-list">
          <div className="thread-search"><Icon name="search" /><span>Rechercher une conversation</span></div>
          {["Teranga Immo", "Mamadou N.", "Keur Habitat"].map((name, i) => (
            <button className={i === 0 ? "thread active" : "thread"} key={name}>
              <span className="thread-avatar">{name.slice(0, 2).toUpperCase()}</span>
              <span><strong>{name}</strong><small>{i === 0 ? "Parfait, à samedi à 10 h." : "Le bien est toujours disponible."}</small></span>
              <time>{i === 0 ? "09:42" : "Hier"}</time>
            </button>
          ))}
        </aside>
        <section className="conversation">
          <div className="conversation-head">
            <div className="agency-line"><span className="agency-avatar">TI</span><div><strong>Teranga Immo</strong><small><Icon name="check" size={13} /> Agence vérifiée</small></div></div>
            <button className="icon-btn"><Icon name="menu" /></button>
          </div>
          <div className="property-strip"><img src={photos.salon} alt="" /><div><strong>Appartement lumineux avec balcon</strong><span>Almadies · 450 000 FCFA / mois</span></div><Freshness /></div>
          <div className="chat">
            <span className="date-separator">Aujourd’hui</span>
            <div className="bubble mine">Bonjour, ce logement est-il toujours disponible ? Je souhaiterais organiser une visite.<time>09:28</time></div>
            <div className="bubble">Bonjour, oui, il est disponible. Nous pouvons vous recevoir samedi matin.<time>09:36</time></div>
            <div className="visit-card">
              <span className="visit-icon"><Icon name="calendar" /></span>
              <div><strong>Visite proposée</strong><span>Samedi 21 juin · 10 h 00</span><small>Almadies, point de rendez-vous envoyé après confirmation.</small></div>
              <Badge tone="success">Confirmée</Badge>
            </div>
            {visit && <div className="feedback-card"><Text as="h3">Comment s’est passée la visite ?</Text><Text>Votre retour aide à maintenir des annonces fiables.</Text><div><Button variant="secondary">Le bien était disponible</Button><Button variant="secondary">Il était conforme</Button></div></div>}
            <div className="bubble mine">Parfait, à samedi à 10 h. Merci.<time>09:42</time></div>
          </div>
          <div className="composer">
            <button className="icon-btn"><Icon name="plus" /></button><input placeholder="Écrivez votre message…" /><Button onClick={() => setVisit(true)}><span className="desktop-only">Envoyer</span><Icon name="arrow" /></Button>
          </div>
        </section>
      </div>
    </main>
  );
}

function PublishScreen() {
  const [step, setStep] = useState(1);
  const labels = ["L’annonce", "Le bien", "Photos", "Publier"];
  return (
    <main className="page container narrow-page">
      <div className="page-heading"><div><Text className="eyebrow">Nouvelle annonce</Text><Text as="h1">Déposer un bien</Text><Text>Vos informations sont enregistrées au fur et à mesure.</Text></div><Badge tone="neutral">Brouillon</Badge></div>
      <div className="stepper">{labels.map((label, i) => <button className={step === i + 1 ? "current" : step > i + 1 ? "done" : ""} onClick={() => setStep(i + 1)} key={label}><span>{step > i + 1 ? <Icon name="check" /> : i + 1}</span><strong>{label}</strong></button>)}</div>
      <div className="form-card">
        {step === 1 && <><Text as="h2">Commençons par l’essentiel</Text><Text className="form-intro">Décrivez clairement l’offre proposée. Vous pourrez la modifier avant l’envoi.</Text><div className="form-grid"><Field label="Titre de l’annonce" value="Appartement lumineux avec balcon" /><Field label="Loyer mensuel" value="450 000 FCFA" /><Field label="Quartier" value="Almadies" /><Field label="Ville" value="Dakar" /></div><label className="field-wrap"><Text className="field-label">Description</Text><textarea defaultValue="Appartement traversant et lumineux dans une résidence calme…" /></label></>}
        {step === 2 && <><Text as="h2">Caractéristiques du bien</Text><Text className="form-intro">Ces informations permettent aux candidats de comparer les logements.</Text><div className="form-grid"><Field label="Type de bien" value="Appartement" /><Field label="Nombre de chambres" value="3" /><Field label="Surface" value="142 m²" /><Field label="Étage" value="3" /></div><div className="info-banner"><Icon name="shield" /><span>La localisation précise ne sera transmise qu’après confirmation d’une visite.</span></div></>}
        {step === 3 && <><Text as="h2">Photos du bien</Text><Text className="form-intro">Ajoutez jusqu’à 15 photos récentes. Format horizontal 4:3 recommandé.</Text><div className="upload-zone"><span><Icon name="plus" /></span><strong>Ajouter des photos</strong><small>JPG ou PNG · 10 Mo maximum par photo</small></div><div className="photo-row"><div><img src={photos.salon} alt="" /><Badge tone="success">Couverture</Badge></div><div className="processing"><Icon name="clock" /><span>Traitement en cours…</span></div></div></>}
        {step === 4 && <><Text as="h2">Prêt pour la modération</Text><Text className="form-intro">Vérifiez les derniers éléments avant de soumettre votre annonce.</Text><div className="readiness"><div><Icon name="check" /><span><strong>Annonce complète</strong><small>Titre, description et prix renseignés</small></span></div><div><Icon name="check" /><span><strong>Bien renseigné</strong><small>Caractéristiques et frais à l’entrée ajoutés</small></span></div><div className="missing"><Icon name="close" /><span><strong>Document manquant</strong><small>Ajoutez un justificatif avant de continuer</small></span><Button variant="secondary">Ajouter</Button></div></div><div className="quota-note"><strong>Votre formule : Essentielle</strong><span>2 annonces publiées sur 3</span></div></>}
        <div className="form-actions"><Button variant="ghost" disabled={step === 1} onClick={() => setStep(Math.max(1, step - 1))}>Retour</Button><Button onClick={() => setStep(Math.min(4, step + 1))}>{step === 4 ? "Soumettre à la modération" : "Continuer"}<Icon name="arrow" /></Button></div>
      </div>
    </main>
  );
}

function AccountScreen({ go }: { go: (screen: Screen) => void }) {
  return (
    <main className="page container">
      <div className="page-heading"><div><Text className="eyebrow">Bonjour Aminata</Text><Text as="h1">Votre espace</Text></div><Button variant="secondary" onClick={() => go("publish")} icon="plus">Déposer une annonce</Button></div>
      <div className="account-grid">
        <aside className="account-nav">{["Profil", "Favoris", "Alertes de recherche", "Notifications", "Mes annonces", "Mon abonnement", "Sécurité"].map((x, i) => <button className={i === 1 ? "active" : ""} key={x}>{x}<Icon name="chevron" /></button>)}</aside>
        <section className="account-content">
          <div className="section-heading"><div><Text as="h2">Vos favoris</Text><Text>3 logements enregistrés</Text></div><Badge tone="info" icon="bell">Alerte quotidienne active</Badge></div>
          <div className="account-list">{listings.slice(0, 3).map((item) => <ListingCard key={item.title} item={item} go={go} horizontal />)}</div>
        </section>
      </div>
    </main>
  );
}

function AgencyScreen({ go }: { go: (screen: Screen) => void }) {
  return (
    <main className="page">
      <section className="agency-hero"><div className="container"><div className="agency-logo">TI</div><div><div className="agency-name"><Text as="h1">Teranga Immo</Text><Badge tone="info" icon="check">Agence vérifiée</Badge></div><Text>Agence immobilière à Dakar · Membre depuis mai 2024</Text><Text className="agency-description">Une équipe locale pour vous accompagner dans votre recherche de logement, avec des informations claires et actualisées.</Text></div><Button variant="secondary" icon="message" onClick={() => go("messages")}>Contacter l’agence</Button></div></section>
      <section className="section container"><div className="section-heading"><div><Text className="eyebrow">Biens publiés</Text><Text as="h2">12 annonces disponibles</Text></div><div className="filter-bar compact"><button>Location</button><button>Plus récentes <Icon name="chevron" /></button></div></div><div className="listing-grid">{listings.slice(0, 3).map((item) => <ListingCard key={item.title} item={item} go={go} />)}</div></section>
    </main>
  );
}

function PricingScreen() {
  return (
    <main className="page container pricing-page">
      <div className="center-heading"><Badge tone="accent">Des offres sans engagement</Badge><Text as="h1">Publiez selon vos besoins</Text><Text>Aucun paiement en ligne. Choisissez une formule, puis transmettez votre justificatif de règlement.</Text></div>
      <div className="pricing-grid">
        <div className="price-card"><Text as="h2">Gratuite</Text><Text>Pour commencer simplement</Text><strong>0 FCFA <span>/ mois</span></strong><ul><li><Icon name="check" />1 annonce active</li><li><Icon name="check" />Messagerie sécurisée</li><li><Icon name="check" />Passeport du Bien</li></ul><Button variant="secondary">Choisir cette formule</Button></div>
        <div className="price-card recommended"><Badge tone="accent">Recommandée</Badge><Text as="h2">Essentielle</Text><Text>Pour les bailleurs réguliers</Text><strong>15 000 FCFA <span>/ mois</span></strong><ul><li><Icon name="check" />3 annonces actives</li><li><Icon name="check" />1 mise en avant incluse</li><li><Icon name="check" />Statistiques essentielles</li></ul><Button>Demander cette formule</Button></div>
        <div className="price-card"><Text as="h2">Agence</Text><Text>Pour travailler en équipe</Text><strong>45 000 FCFA <span>/ mois</span></strong><ul><li><Icon name="check" />15 annonces actives</li><li><Icon name="check" />3 membres d’équipe</li><li><Icon name="check" />Suivi des demandes</li></ul><Button variant="secondary">Contacter l’équipe</Button></div>
      </div>
      <div className="offline-payment"><span><Icon name="building" /></span><div><Text as="h2">Paiement hors ligne, confirmation humaine</Text><Text>Réglez par virement ou mobile money, puis déclarez le règlement et joignez le justificatif. Votre formule est activée après vérification.</Text></div><Button variant="secondary">Voir les instructions</Button></div>
    </main>
  );
}

function AdminScreen() {
  return (
    <main className="admin-page">
      <aside className="admin-sidebar"><Logo onClick={() => {}} /><Text className="admin-label">Administration</Text>{["Vue d’ensemble", "Modération", "Signalements", "Utilisateurs", "Notifications", "Règlements"].map((x, i) => <button className={i === 0 ? "active" : ""} key={x}><Icon name={i === 0 ? "home" : i === 1 ? "shield" : i === 3 ? "user" : "bell"} />{x}</button>)}</aside>
      <section className="admin-main">
        <div className="page-heading"><div><Text className="eyebrow">Mardi 17 juin 2025</Text><Text as="h1">Vue d’ensemble</Text></div><div className="admin-profile"><span>AM</span><div><strong>Awa Mbaye</strong><small>Administratrice</small></div></div></div>
        <div className="metrics">
          <div><span>Revenu confirmé</span><strong>8 450 000 FCFA</strong><small className="positive">+ 8,2 % ce mois</small></div>
          <div className="pending"><span>Déclaré, non confirmé</span><strong>1 275 000 FCFA</strong><small>18 règlements à vérifier</small></div>
          <div><span>Annonces publiées</span><strong>623</strong><small>41 à reconfirmer</small></div>
          <div><span>En modération</span><strong>27</strong><small>6 depuis plus de 24 h</small></div>
        </div>
        <div className="admin-grid">
          <div className="admin-card"><div className="panel-title"><div><Text as="h2">À modérer</Text><Text>Une note est obligatoire avant chaque action.</Text></div><Button variant="ghost">Voir la file <Icon name="arrow" /></Button></div><div className="admin-table"><div className="table-head"><span>Référence</span><span>Annonce</span><span>Annonceur</span><span>Attente</span><span>État</span></div>{[["DK-ALM-002461", "Villa 4 ch. · Almadies", "Sunu Habitat", "2 h", "À vérifier"], ["DK-YOF-002460", "Studio · Yoff", "M. Diop", "5 h", "Nouveau"], ["DK-MER-002459", "Appartement 3 ch.", "Teranga Immo", "18 h", "Document"]].map((r) => <div className="table-row" key={r[0]}>{r.map((x, i) => <span key={x}>{i === 4 ? <Badge tone={x === "Nouveau" ? "info" : "warning"}>{x}</Badge> : x}</span>)}</div>)}</div></div>
          <div className="admin-card"><div className="panel-title"><Text as="h2">Santé de la plateforme</Text><Badge tone="success">Stable</Badge></div><div className="health-list">{[["Annonces fraîches", "91 %"], ["Messages délivrés", "99,4 %"], ["Téléphones vérifiés", "96 %"], ["Signalements ouverts", "14"]].map(([a, b]) => <div key={a}><span>{a}</span><strong>{b}</strong></div>)}</div><div className="info-banner"><Icon name="shield" /><span>Double authentification active pour les 8 administrateurs.</span></div></div>
        </div>
      </section>
    </main>
  );
}

function SystemScreen() {
  return (
    <main className="page container system-page">
      <div className="page-heading"><div><Text className="eyebrow">ChezVous Foundations v1.0</Text><Text as="h1">Design system</Text><Text>Variables partagées pour Next.js + Tailwind et Flutter.</Text></div><Badge tone="success">AA minimum</Badge></div>
      <section className="system-section"><Text as="h2">Couleurs sémantiques</Text><div className="swatches">{[["Primaire", "var(--primary)", "#0D4A3E"], ["Sable", "var(--sand)", "#F6F1E8"], ["Terre cuite", "var(--accent)", "#B84F32"], ["Encre", "var(--ink)", "#17201D"], ["Information", "var(--info)", "#205EA6"], ["Attention", "var(--warning)", "#9A5B06"], ["Erreur", "var(--danger)", "#B42318"]].map(([name, variable, hex]) => <div key={name}><span style={{ background: hex }} /><strong>{name}</strong><small>{variable}</small></div>)}</div></section>
      <section className="system-section"><Text as="h2">Typographie & espacements</Text><div className="type-grid"><div><Text className="display-sample">Trouver sereinement.</Text><small>Display · 48/52 · 700</small></div><div><Text as="h2">Un titre de section clair</Text><small>Heading 2 · 30/36 · 700</small></div><div><Text>Texte courant pensé pour rester lisible sur un écran mobile en plein soleil.</Text><small>Body · 16/24 · 400</small></div></div><div className="spacing-scale">{[4, 8, 12, 16, 24, 32, 48].map((x) => <div key={x}><span style={{ width: x * 2 }} /><small>{x}</small></div>)}</div></section>
      <section className="system-section"><Text as="h2">Boutons, badges et champs</Text><div className="component-demo"><Button>Action principale</Button><Button variant="secondary">Action secondaire</Button><Button variant="terracotta">Action accent</Button><Button variant="danger">Supprimer</Button><Button disabled>Chargement…</Button></div><div className="component-demo"><Freshness /><Freshness state="reserved" /><Freshness state="reconfirm" /><Freshness state="old" /></div><div className="demo-fields"><Field label="Téléphone" value="+221 77 123 45 67" /><Field label="Budget" value="450 000 FCFA" /><Field label="Mot de passe" value="••••••••••" /></div></section>
      <section className="system-section"><Text as="h2">États produit</Text><div className="state-grid"><div className="state-card"><span className="state-icon"><Icon name="search" /></span><strong>Aucun résultat</strong><small>Élargissez la zone ou augmentez le budget.</small><Button variant="ghost">Modifier les filtres</Button></div><div className="state-card"><span className="state-icon error"><Icon name="close" /></span><strong>Connexion impossible</strong><small>Vérifiez votre réseau et réessayez.</small><Button variant="secondary">Réessayer</Button></div><div className="state-card"><span className="state-icon warning"><Icon name="bell" /></span><strong>Votre formule est pleine</strong><small>Vous avez atteint votre quota d’annonces.</small><Button variant="ghost">Voir les offres</Button></div><div className="skeleton-card"><span /><span /><span /><span /></div></div></section>
      <section className="system-section dev-notes"><Text as="h2">Notes de développement</Text><div><span><strong>Responsive</strong><small>390 px mobile, rupture principale à 768 px, conteneur 1180 px sur desktop.</small></span><span><strong>Grille</strong><small>4 colonnes mobile, 12 desktop. Gouttières 16 / 24 px.</small></span><span><strong>Interactions</strong><small>Cibles tactiles ≥ 44 px. Focus visible. Les états ne reposent jamais sur la couleur seule.</small></span><span><strong>Flutter</strong><small>Mapper les variables CSS vers ThemeExtension : colors, spacing, radius, elevation et textTheme.</small></span></div></section>
    </main>
  );
}

function BottomNav({ screen, go }: { screen: Screen; go: (screen: Screen) => void }) {
  return (
    <nav className="bottom-nav" aria-label="Navigation mobile">
      {[["home", "home", "Accueil"], ["results", "search", "Rechercher"], ["publish", "plus", "Publier"], ["messages", "message", "Messages"], ["account", "user", "Compte"]].map(([value, icon, label]) => <button className={screen === value ? "active" : ""} onClick={() => go(value as Screen)} key={value}><Icon name={icon as IconName} /><span>{label}</span></button>)}
    </nav>
  );
}

export default function App() {
  const [screen, setScreen] = useState<Screen>("home");
  const go = (next: Screen) => {
    setScreen(next);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };
  const content: Record<Screen, ReactNode> = {
    home: <HomeScreen go={go} />,
    results: <ResultsScreen go={go} />,
    detail: <DetailScreen go={go} />,
    contact: <DetailScreen go={go} />,
    messages: <MessagesScreen />,
    publish: <PublishScreen />,
    account: <AccountScreen go={go} />,
    agency: <AgencyScreen go={go} />,
    pricing: <PricingScreen />,
    admin: <AdminScreen />,
    system: <SystemScreen />,
  };
  return (
    <div className="app-shell">
      {screen !== "admin" && <Header screen={screen} go={go} />}
      {content[screen]}
      {screen !== "admin" && <BottomNav screen={screen} go={go} />}
      <button className="prototype-switcher" onClick={() => go("system")} title="Ouvrir le design system"><Icon name="sun" /></button>
    </div>
  );
}
