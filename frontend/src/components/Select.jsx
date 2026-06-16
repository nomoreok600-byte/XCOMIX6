"use client";

import { useEffect, useRef, useState } from "react";
import Icon from "./Icon";

/**
 * Custom, on-brand dropdown that replaces native <select> (which renders as the
 * unstyled Chrome/OS control). Supports an optional search box for long lists
 * (e.g. the reader's chapter picker) and an upward-opening variant for bars
 * pinned to the bottom of the screen.
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
  const ref = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const onDoc = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
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

  return (
    <div className={`xselect ${className} ${open ? "open" : ""} ${up ? "up" : ""}`} ref={ref}>
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
      {open && (
        <div className="xselect-menu" role="listbox">
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
        </div>
      )}
    </div>
  );
}
