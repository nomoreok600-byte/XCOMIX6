"use client";

// Professional XCOMIX logo: a gradient badge mark (stacked comic pages forming an
// "X") plus the wordmark. The gradient pulls from the active theme CSS vars so it
// recolors with the user's chosen palette.
export default function Logo({ size = 30, showWord = true, className = "" }) {
  const gid = "xcomix-logo-grad";
  return (
    <span className={`logo ${className}`} style={{ display: "inline-flex", alignItems: "center", gap: 10 }}>
      <svg
        width={size}
        height={size}
        viewBox="0 0 48 48"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
        style={{ flexShrink: 0 }}
      >
        <defs>
          <linearGradient id={gid} x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
            <stop stopColor="var(--crimson)" />
            <stop offset="1" stopColor="var(--crimson-2)" />
          </linearGradient>
        </defs>
        <rect x="3" y="3" width="42" height="42" rx="12" fill={`url(#${gid})`} />
        <rect x="3" y="3" width="42" height="42" rx="12" fill="black" opacity="0.12" />
        {/* Stylized X built from two bold strokes with a page-fold notch */}
        <path
          d="M16 14 L24 23 L32 14 L37 14 L27.5 24 L37 34 L32 34 L24 25 L16 34 L11 34 L20.5 24 L11 14 Z"
          fill="#fff"
          fillOpacity="0.96"
        />
      </svg>
      {showWord && (
        <span className="logo-word">
          <span className="logo-x">X</span>COMIX
        </span>
      )}
    </span>
  );
}
