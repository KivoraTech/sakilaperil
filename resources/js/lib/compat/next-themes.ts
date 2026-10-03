/** Stand-in for `next-themes`: the admin is light-only (as in the reference). */
export function useTheme() {
  return { theme: "light", resolvedTheme: "light", setTheme: (_t: string) => undefined, themes: ["light"] };
}
export function ThemeProvider({ children }: { children: React.ReactNode }) {
  return children;
}
