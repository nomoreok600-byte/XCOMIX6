"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

const STORAGE_KEY = "headless_manga_state_v1";

const DEFAULT_STATE = {
  history: {},
  bookmarks: {},
  preferences: {
    readerMode: "scroll",
    zoom: 100,
    gaps: false,
    grayscale: false,
    invert: false,
    brightness: 100,
    dataSaver: false,
  },
  pendingSync: [],
};

function readState() {
  if (typeof window === "undefined") return DEFAULT_STATE;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return DEFAULT_STATE;
    return { ...DEFAULT_STATE, ...JSON.parse(raw) };
  } catch {
    return DEFAULT_STATE;
  }
}

function writeState(nextState) {
  if (typeof window === "undefined") return;
  localStorage.setItem(STORAGE_KEY, JSON.stringify(nextState));
}

export function useMangaLibraryState({ userId, syncEndpoint = "/api/headless/sync" } = {}) {
  const [state, setState] = useState(DEFAULT_STATE);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    setState(readState());
    setHydrated(true);
  }, []);

  const updateState = useCallback((recipe, syncEvent) => {
    setState((current) => {
      const next = recipe(current);
      const withSync = syncEvent
        ? { ...next, pendingSync: [...(next.pendingSync || []), syncEvent] }
        : next;
      writeState(withSync);
      return withSync;
    });
  }, []);

  const markChapterRead = useCallback(
    ({ mangaId, chapterId, chapterNumber, href, title, cover }) => {
      updateState(
        (current) => ({
          ...current,
          history: {
            ...current.history,
            [mangaId]: {
              mangaId,
              chapterId,
              chapterNumber,
              href,
              title,
              cover,
              timestamp: Date.now(),
            },
          },
        }),
        { type: "history", mangaId, chapterId, chapterNumber, href, timestamp: Date.now() }
      );
    },
    [updateState]
  );

  const toggleBookmark = useCallback(
    ({ mangaId, title, cover }) => {
      updateState(
        (current) => {
          const exists = Boolean(current.bookmarks?.[mangaId]);
          const bookmarks = { ...(current.bookmarks || {}) };
          if (exists) delete bookmarks[mangaId];
          else bookmarks[mangaId] = { mangaId, title, cover, timestamp: Date.now() };
          return { ...current, bookmarks };
        },
        { type: "bookmark", mangaId, title, cover, timestamp: Date.now() }
      );
    },
    [updateState]
  );

  const updatePreferences = useCallback(
    (preferences) => {
      updateState(
        (current) => ({
          ...current,
          preferences: { ...current.preferences, ...preferences },
        }),
        { type: "preferences", preferences, timestamp: Date.now() }
      );
    },
    [updateState]
  );

  const flushSync = useCallback(async () => {
    if (!userId || !state.pendingSync?.length) return false;
    const payload = { userId, events: state.pendingSync, snapshot: state };
    const response = await fetch(syncEndpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    if (!response.ok) return false;
    updateState((current) => ({ ...current, pendingSync: [] }));
    return true;
  }, [state, syncEndpoint, updateState, userId]);

  useEffect(() => {
    if (userId && hydrated && state.pendingSync?.length) {
      flushSync().catch(() => {});
    }
  }, [flushSync, hydrated, state.pendingSync?.length, userId]);

  return useMemo(
    () => ({
      hydrated,
      history: state.history || {},
      bookmarks: state.bookmarks || {},
      preferences: state.preferences || DEFAULT_STATE.preferences,
      isBookmarked: (mangaId) => Boolean(state.bookmarks?.[mangaId]),
      markChapterRead,
      toggleBookmark,
      updatePreferences,
      flushSync,
    }),
    [flushSync, hydrated, markChapterRead, state, toggleBookmark, updatePreferences]
  );
}
