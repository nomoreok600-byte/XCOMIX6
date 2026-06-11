export default [
  {
    ignores: [".next/**", "node_modules/**", "wordpress-themes/**"],
  },
  {
    files: ["app/**/*.{js,jsx}"],
    languageOptions: {
      ecmaVersion: 2024,
      sourceType: "module",
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        console: "readonly",
        document: "readonly",
        fetch: "readonly",
        localStorage: "readonly",
        Math: "readonly",
        process: "readonly",
        Response: "readonly",
        URL: "readonly",
        window: "readonly",
      },
    },
    rules: {},
  },
];
