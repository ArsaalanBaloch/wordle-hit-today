import globals from "globals";

export default [
  {
    ignores: ["var/**", "vendor/**", "node_modules/**", "assets/dist/**"],
  },
  {
    files: ["**/*.js"],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    rules: {
      "no-console": ["error", { "allow": ["warn", "error"] }],
      "no-eval": "error",
      "no-implied-eval": "error",
      "prefer-const": "error"
    },
  },
];
