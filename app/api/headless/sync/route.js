export const runtime = "nodejs";

export async function POST(request) {
  const payload = await request.json().catch(() => null);
  if (!payload?.userId || !Array.isArray(payload?.events)) {
    return Response.json({ error: "Invalid sync payload" }, { status: 400 });
  }

  const wpBase =
    process.env.WORDPRESS_API_URL ||
    process.env.NEXT_PUBLIC_WORDPRESS_API_URL ||
    process.env.NEXT_PUBLIC_WP_API_URL;
  const wpPrefix = process.env.WORDPRESS_API_PREFIX || "/wp-json/xcomix/v1";
  const authToken = request.headers.get("authorization") || process.env.WORDPRESS_SYNC_TOKEN;

  if (wpBase && authToken) {
    const endpoint = `${wpBase.replace(/\/+$/, "")}/${wpPrefix.replace(/^\/+|\/+$/g, "")}/user-state`;
    const upstream = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: authToken,
      },
      body: JSON.stringify(payload),
      cache: "no-store",
    });

    if (!upstream.ok) {
      return Response.json({ error: "WordPress sync failed" }, { status: upstream.status });
    }
  }

  // Auth-adapter ready: validate a NextAuth/Clerk session here before forwarding
  // events to WordPress in production.
  return Response.json({
    ok: true,
    accepted: payload.events.length,
  });
}
