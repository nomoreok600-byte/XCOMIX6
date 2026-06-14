"use client";

export function CardSkeleton() {
  return (
    <div className="card">
      <div className="skeleton skeleton-cover" />
      <div className="card-body">
        <div className="skeleton skeleton-line" style={{ width: "90%" }} />
        <div className="skeleton skeleton-line" style={{ width: "55%" }} />
      </div>
    </div>
  );
}

export function GridSkeleton({ count = 12 }) {
  return (
    <div className="grid">
      {Array.from({ length: count }).map((_, i) => (
        <CardSkeleton key={i} />
      ))}
    </div>
  );
}
