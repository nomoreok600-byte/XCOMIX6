"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { fetchSiteConfig } from "./api";

const DEFAULT_CONFIG = {
  ads: {},
  announcement: { enabled: false, items: [], text: "" },
  redirect: { enabled: false, urls: [], url: "", cooldownMin: 2 },
};

const SiteConfigContext = createContext(DEFAULT_CONFIG);

export function SiteConfigProvider({ children }) {
  const [config, setConfig] = useState(DEFAULT_CONFIG);

  useEffect(() => {
    let active = true;
    fetchSiteConfig()
      .then((c) => active && c && setConfig(c))
      .catch(() => {});
    return () => {
      active = false;
    };
  }, []);

  return <SiteConfigContext.Provider value={config}>{children}</SiteConfigContext.Provider>;
}

export function useSiteConfig() {
  return useContext(SiteConfigContext);
}
