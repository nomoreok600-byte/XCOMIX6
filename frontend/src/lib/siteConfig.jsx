"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { fetchSiteConfig } from "./api";

const SiteConfigContext = createContext({ ads: {}, announcement: { enabled: false, text: "" } });

export function SiteConfigProvider({ children }) {
  const [config, setConfig] = useState({ ads: {}, announcement: { enabled: false, text: "" } });

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
