export const runtime = "nodejs";

export async function POST(request) {
  const payload = await request.json().catch(() => null);
  if (!payload?.userId || !Array.isArray(payload?.events)) {
    return Response.json({ error: "Invalid sync payload" }, { status: 400 });
  }

  // This endpoint is intentionally auth-adapter ready. Once NextAuth/Clerk is
  // wired in, validate the session here and forward payload.events to the
  // WordPress custom user tracking endpoints.
  return Response.json({
    ok: true,
    accepted: payload.events.length,
  });
}
