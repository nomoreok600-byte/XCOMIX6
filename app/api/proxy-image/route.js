export const runtime = "nodejs";
export const dynamic = "force-dynamic";

const DESKTOP_HEADERS = [
  {
    "User-Agent":
      "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
    "Accept-Language": "en-US,en;q=0.9",
  },
  {
    "User-Agent":
      "Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15",
    "Accept-Language": "en-US,en;q=0.9",
  },
  {
    "User-Agent":
      "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
    "Accept-Language": "en-US,en;q=0.9",
  },
];

function isPrivateIp(hostname) {
  if (hostname === "localhost") return true;
  if (/^127\./.test(hostname)) return true;
  if (/^10\./.test(hostname)) return true;
  if (/^192\.168\./.test(hostname)) return true;
  if (/^172\.(1[6-9]|2\d|3[0-1])\./.test(hostname)) return true;
  if (hostname === "::1" || hostname.startsWith("fc") || hostname.startsWith("fd")) return true;
  return false;
}

function refererFor(url) {
  const host = url.hostname.replace(/^www\./, "");
  if (host.includes("mangakatana")) return "https://mangakatana.com/";
  if (host.includes("manhwabuddy")) return "https://manhwabuddy.com/";
  if (host.includes("mgeko") || host.includes("mangageko")) return `https://${url.hostname}/`;
  return `${url.protocol}//${url.hostname}/`;
}

export async function GET(request) {
  const { searchParams } = new URL(request.url);
  const target = searchParams.get("url");

  if (!target) {
    return Response.json({ error: "Missing url parameter" }, { status: 400 });
  }

  let targetUrl;
  try {
    targetUrl = new URL(target);
  } catch {
    return Response.json({ error: "Invalid url parameter" }, { status: 400 });
  }

  if (!["http:", "https:"].includes(targetUrl.protocol) || isPrivateIp(targetUrl.hostname)) {
    return Response.json({ error: "Blocked target" }, { status: 400 });
  }

  const desktopHeader = DESKTOP_HEADERS[Math.floor(Math.random() * DESKTOP_HEADERS.length)];

  // cPanel Node apps run with tight per-process memory and a small pool of
  // concurrent connections. Guard the time-to-first-byte with an AbortController
  // so a slow/hung upstream can't pin a worker indefinitely. The timeout is
  // cleared once headers arrive; the body itself is streamed through untouched
  // (never buffered into memory) so large chapter pages stay low-overhead.
  const controller = new AbortController();
  const headerTimeout = setTimeout(() => controller.abort(), 20000);

  let upstream;
  try {
    upstream = await fetch(targetUrl, {
      headers: {
        ...desktopHeader,
        Accept: "image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8",
        Referer: refererFor(targetUrl),
        Origin: `${targetUrl.protocol}//${targetUrl.hostname}`,
        Connection: "keep-alive",
        "Upgrade-Insecure-Requests": "1",
      },
      cache: "no-store",
      redirect: "follow",
      signal: controller.signal,
    });
  } catch (error) {
    return Response.json(
      { error: "Upstream image request failed", detail: error?.name === "AbortError" ? "timeout" : error?.message },
      { status: 504 }
    );
  } finally {
    clearTimeout(headerTimeout);
  }

  if (!upstream.ok || !upstream.body) {
    return Response.json({ error: "Unable to fetch image" }, { status: upstream.status || 502 });
  }

  const contentType = upstream.headers.get("content-type") || "image/jpeg";
  const cacheControl = contentType.startsWith("image/")
    ? "public, max-age=86400, s-maxage=604800, stale-while-revalidate=604800"
    : "no-store";

  const headers = {
    "Content-Type": contentType,
    "Cache-Control": cacheControl,
    "Access-Control-Allow-Origin": "*",
    "X-Content-Type-Options": "nosniff",
  };

  // Forward length/range hints when present so the platform can stream the
  // response progressively instead of materializing the whole image.
  const contentLength = upstream.headers.get("content-length");
  if (contentLength) headers["Content-Length"] = contentLength;
  const acceptRanges = upstream.headers.get("accept-ranges");
  if (acceptRanges) headers["Accept-Ranges"] = acceptRanges;

  // Stream the upstream body straight through (no arrayBuffer/Buffer copy).
  return new Response(upstream.body, { status: 200, headers });
}
