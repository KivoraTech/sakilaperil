import js from "@eslint/js";
import prettier from "eslint-config-prettier";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";
import tseslint from "typescript-eslint";

export default tseslint.config(
  { ignores: ["public/**", "vendor/**", "node_modules/**", "bootstrap/ssr/**", "storage/**"] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ["resources/js/**/*.{ts,tsx}", "scripts/**/*.ts", "tests/**/*.ts", "*.ts"],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
    plugins: { "react-hooks": reactHooks },
    rules: {
      ...reactHooks.configs.recommended.rules,
      "@typescript-eslint/no-unused-vars": ["error", { argsIgnorePattern: "^_", varsIgnorePattern: "^_", ignoreRestSiblings: true }],
    },
  },
  prettier,
);
