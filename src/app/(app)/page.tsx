import Link from "next/link";

const ICONS: Record<string, React.ReactNode> = {
  sale: (
    <>
      <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z" />
      <path d="M9 8h6M9 12h6" />
    </>
  ),
  "cf-sales": (
    <>
      <path d="M2 6h12v11H2z" />
      <path d="M14 10h4l4 4v3h-8z" />
      <circle cx="7" cy="18.5" r="1.8" />
      <circle cx="18" cy="18.5" r="1.8" />
    </>
  ),
  purchase: (
    <>
      <circle cx="9" cy="20" r="1.5" />
      <circle cx="18" cy="20" r="1.5" />
      <path d="M2 3h3l2.6 12.2a2 2 0 0 0 2 1.6h8.2a2 2 0 0 0 2-1.6L21.5 7H6" />
    </>
  ),
};

const ROWS = [
  { slug: "sale", label: "Sale" },
  { slug: "cf-sales", label: "C&F Sales" },
  { slug: "purchase", label: "Purchase" },
];

export default function HomePage() {
  return (
    <main className="home">
      <div className="home-bg" aria-hidden="true" />
      <div className="home-rows">
        {ROWS.map((row) => (
          <Link key={row.slug} href={`/r/${row.slug}`} className={`home-row ${row.slug}`}>
            <span className="home-icon">
              <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                {ICONS[row.slug]}
              </svg>
            </span>
            <span className="home-label">{row.label}</span>
            <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M9 5l7 7-7 7" />
            </svg>
          </Link>
        ))}
      </div>
    </main>
  );
}
