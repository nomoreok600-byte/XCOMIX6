"use client";

import { useCallback, useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import Icon from "./Icon";

/**
 * Custom, on-brand dropdown that replaces native <select> (which renders as the
 * unstyled Chrome/OS control). Supports an optional search box for long lists
 * (e.g. the reader's chapter picker) and an upward-opening variant for bars
 * pinned to the bottom of the screen.
 *
 * The open menu is rendered in a portal on <body> with FIXED positioning so it
 * is never clipped by an ancestor's `overflow:hidden` or trapped beneath a
 * lower stacking context (this is what made the manga page's "Add to library"
 * options hide behind the hero/about box).
 *
 * options: Array<{ value: string|number, label: string }>
 */
export default function Select({
  value,
  onChange,
  options = [],
  placeholder = "Select",
  className = "",
  ariaLabel,
  searchable = false,
  up = false,
}) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [mounted, setMounted] = useState(false);
  const [rect, setRect] = useState(null);
  const triggerRef = useRef(null);
  const menuRef = useRef(null);

  useEffect(() => setMounted(true), []);

  const measure = useCallback(() => {
    const el = triggerRef.current;
    if (!el) return;
    const r = el.getBoundingClientRect();
    setRect({ left: r.left, top: r.top, bottom: r.bottom, width: r.width });
  }, []);

  // Measure synchronously the moment we open (before paint) so the menu never
  // flashes in the wrong spot, then keep it pinned to the trigger on scroll/resize.
  useLayoutEffect(() => {
    if (!open) return undefined;
    measure();
    const onMove = () => measure();
    window.addEventListener("scroll", onMove, true);
    window.addEventListener("resize", onMove);
    return () => {
      window.removeEventListener("scroll", onMove, true);
      window.removeEventListener("resize", onMove);
    };
  }, [open, measure]);

  useEffect(() => {
    if (!open) return undefined;
    const onDoc = (e) => {
      if (triggerRef.current?.contains(e.target)) return;
      if (menuRef.current?.contains(e.target)) return;
      setOpen(false);
    };
    const onKey = (e) => {
      if (e.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", onDoc);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDoc);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const selected = options.find((o) => String(o.value) === String(value));
  const showSearch = searchable && options.length > 8;
  const filtered = q
    ? options.filter((o) => String(o.label).toLowerCase().includes(q.toLowerCase()))
    : options;

  const menuStyle = rect
    ? {
        position: "fixed",
        left: rect.left,
        width: rect.width,
        ...(up
          ? { bottom: Math.max(8, window.innerHeight - rect.top + 6) }
          : { top: rect.bottom + 6 }),
      }
    : { position: "fixed", left: -9999, top: -9999 };

  return (
    <div className={`xselect ${className} ${open ? "open" : ""} ${up ? "up" : ""}`} ref={triggerRef}>
      <button
        type="button"
        className="xselect-trigger"
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-label={ariaLabel}
      >
        <span className="xselect-label">{selected ? selected.label : placeholder}</span>
        <Icon name="chevronDown" size={16} className="xselect-caret" />
      </button>
      {open && mounted &&
        createPortal(
          <div
            className={`xselect-menu xselect-portal ${up ? "up" : ""}`}
            role="listbox"
            ref={menuRef}
            style={menuStyle}
          >
            {showSearch && (
              <input
                className="xselect-search"
                autoFocus
                placeholder="Search…"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                onClick={(e) => e.stopPropagation()}
              />
            )}
            <div className="xselect-options">
              {filtered.map((o) => {
                const active = String(o.value) === String(value);
                return (
                  <button
                    key={o.value}
                    type="button"
                    role="option"
                    aria-selected={active}
                    className={`xselect-option ${active ? "active" : ""}`}
                    onClick={() => {
                      onChange(o.value);
                      setOpen(false);
                      setQ("");
                    }}
                  >
                    <span className="xselect-option-label">{o.label}</span>
                    {active && <Icon name="check" size={15} />}
                  </button>
                );
              })}
              {filtered.length === 0 && <div className="xselect-empty">No matches</div>}
            </div>
          </div>,
          document.body
        )}
    </div>
  );
}
