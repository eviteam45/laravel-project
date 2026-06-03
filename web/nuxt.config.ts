// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({

  modules: ['@nuxtjs/tailwindcss', '@nuxt/eslint'],
  devtools: { enabled: true },

  runtimeConfig: {
    public: {
      // Base URL of the Laravel API. Override with NUXT_PUBLIC_API_BASE.
      apiBase: 'http://localhost:8000/api',
    },
  },
  compatibilityDate: '2024-11-01',

  eslint: {
    config: {
      stylistic: true, // Prettier-style formatting rules via @stylistic
    },
  },
})
