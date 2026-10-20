"use client";

import { useEffect, useState } from "react";
import { usePathname } from "next/navigation";
import Icon from "./Icon";
import Logo from "./Logo";

const DISMISS_KEY = "xcomix_install_dismissed";

function isStandalone() {
  if (typeof window === "undefined") return false;
  return (
    window.matchMedia?.("(display-mode: standalone)")?.matches ||
    window.navigator.standalone === true
  );
}
function isIOS() {
  if (typeof navigator === "undefined") return false;
  return /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
}

// In-app "Install app" prompt for the PWA. On Chromium (Android/desktop) it
// captures the native `beforeinstallprompt` and triggers the real install
// dialog; on iOS Safari (no such event) it shows Add-to-Home-Screen steps. The
// banner is dismissible (remembered) and never shows once installed.
export default function InstallPrompt() {
  const pathname = usePathname();
  const [deferred, setDeferred] = useState(null);
  const [show, setShow] = useState(false);
  const [iosHelp, setIosHelp] = useState(false);

  useEffect(() => {
    if (isStandalone()) return undefined;
    let dismissed = false;
    try {
      dismissed = window.localStorage.getItem(DISMISS_KEY) === "1";
    } catch {
      dismissed = false;
    }
    if (dismissed) return undefined;

    const onBeforeInstall = (e) => {
      e.preventDefault();
      setDeferred(e);
      setShow(true);
    };
    const onInstalled = () => {
      setShow(false);
      setDeferred(null);
      try {
        window.localStorage.setItem(DISMISS_KEY, "1");
      } catch {
        /* ignore */
      }
    };
    window.addEventListener("beforeinstallprompt", onBeforeInstall);
    window.addEventListener("appinstalled", onInstalled);

    // iOS Safari can't use beforeinstallprompt — offer manual steps instead.
    if (isIOS()) setShow(true);

    return () => {
      window.removeEventListener("beforeinstallprompt", onBeforeInstall);
      window.removeEventListener("appinstalled", onInstalled);
    };
  }, []);

  // Keep the reader immersive and don't clutter the auth pages.
  if (
    pathname?.startsWith("/reader") ||
    pathname?.startsWith("/login") ||
    pathname?.startsWith("/register")
  ) {
    return null;
  }
  if (!show) return null;

  const dismiss = () => {
    setShow(false);
    setIosHelp(false);
    try {
      window.localStorage.setItem(DISMISS_KEY, "1");
    } catch {
      /* ignore */
    }
  };

  const install = async () => {
    if (deferred) {
      deferred.prompt();
      try {
        await deferred.userChoice;
      } catch {
        /* ignore */
      }
      setDeferred(null);
      setShow(false);
    } else if (isIOS()) {
      setIosHelp(true);
    }
  };

  return (
    <>
      <div className="install-banner" role="dialog" aria-label="Install the XCOMIX app">
        <div className="install-banner-icon"><Logo size={28} /></div>
        <div className="install-banner-text">
          <strong>Install the XCOMIX app</strong>
          <span>Add it to your home screen for full-screen, app-like reading.</span>
        </div>
        <button className="btn btn-primary install-banner-btn" onClick={install}>
          <Icon name="download" size={16} /> Install
        </button>
        <button className="install-banner-close" onClick={dismiss} aria-label="Dismiss">
          <Icon name="close" size={16} />
        </button>
      </div>

      {iosHelp && (
        <div className="install-ios-overlay" onClick={() => setIosHelp(false)} role="presentation">
          <div className="install-ios-sheet" onClick={(e) => e.stopPropagation()} role="dialog" aria-label="Install on iOS">
            <h3><Icon name="download" size={18} /> Install on iPhone / iPad</h3>
            <ol>
              <li>Tap the <b>Share</b> icon in Safari&apos;s toolbar.</li>
              <li>Scroll down and tap <b>Add to Home Screen</b>.</li>
              <li>Tap <b>Add</b> — XCOMIX appears as an app icon.</li>
            </ol>
            <button className="btn btn-primary" onClick={() => setIosHelp(false)}>Got it</button>
          </div>
        </div>
      )}
    </>
  );
}
