"use client";

export default function Stars({ value = 0, onChange }) {
  const editable = typeof onChange === "function";
  return (
    <span className={`stars${editable ? " input" : ""}`}>
      {[1, 2, 3, 4, 5].map((n) => (
        <span
          key={n}
          className={`star${n <= Math.round(value) ? " on" : ""}`}
          onClick={editable ? () => onChange(n) : undefined}
          role={editable ? "button" : undefined}
        >
          ★
        </span>
      ))}
    </span>
  );
}
