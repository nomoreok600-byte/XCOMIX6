import Link from "next/link";
import Logo from "../components/Logo";

// Rendered to out/404.html by the static export. The bundled .htaccess points
// the host's ErrorDocument 404 here, so unknown URLs show this branded page
// instead of the raw cPanel/LiteSpeed 404.
export const metadata = { title: "Page not found" };

export default function NotFound() {
  return (
    <main className="notfound">
      <Logo size={52} />
      <div className="notfound-code">404</div>
      <h1>Page not found</h1>
      <p>The page you’re looking for moved or never existed. Let’s get you back to reading.</p>
      <div className="landing-actions">
        <Link href="/home" className="btn btn-primary">Go to homepage</Link>
        <Link href="/browse" className="btn btn-ghost">Browse manga</Link>
      </div>
    </main>
  );
}
