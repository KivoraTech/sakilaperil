"use client";

import { createContext, useContext } from "react";
import type { BuilderReferences } from "@/lib/queries/admin/pages";

export type LinkSuggestion = { href: string; label: string };

export type EditorReferences = BuilderReferences & { linkSuggestions: LinkSuggestion[] };

const EMPTY: EditorReferences = {
  images: [],
  projects: [],
  albums: [],
  newsCategories: [],
  linkSuggestions: [],
};

const ReferencesContext = createContext<EditorReferences>(EMPTY);

export function ReferencesProvider({
  value,
  children,
}: {
  value: EditorReferences;
  children: React.ReactNode;
}) {
  return <ReferencesContext.Provider value={value}>{children}</ReferencesContext.Provider>;
}

export function useReferences() {
  return useContext(ReferencesContext);
}
