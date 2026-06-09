import {process} from "std-env";

const apiBase = process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api'
const apiOrigin = new URL(apiBase).origin

const securityHeaders = {
  'Content-Security-Policy': [
    'default-src \'self\'',
    'script-src \'self\' \'unsafe-inline\'',
    'style-src \'self\' \'unsafe-inline\'',
    'img-src \'self\' data: blob:',
    `connect-src 'self' ${apiOrigin}`,
    'font-src \'self\' data:',
    'object-src \'none\'',
    'base-uri \'self\'',
    'form-action \'self\'',
    'frame-ancestors \'none\'',
  ].join('; '),
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Permissions-Policy': 'geolocation=(), microphone=(), camera=()',
}

export default defineNuxtConfig({

  modules: ['@nuxtjs/tailwindcss', '@nuxt/eslint', '@pinia/nuxt'],
  devtools: { enabled: true },

  runtimeConfig: {
    public: {
      apiBase,
    },
  },

  routeRules: {
    '/**': { headers: securityHeaders },
  },
  compatibilityDate: '2024-11-01',

  eslint: {
    config: {
      stylistic: true,
    },
  },
})
