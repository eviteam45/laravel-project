// https://eslint.nuxt.com — extends the auto-generated, Nuxt-aware flat config.
import withNuxt from './.nuxt/eslint.config.mjs'

// Node 20 lacks Object.groupBy (added in Node 21), which eslint-flat-config-utils uses.
if (typeof Object.groupBy !== 'function') {
  Object.groupBy = (items, keyFn) => {
    const out = {}
    let i = 0
    for (const item of items) {
      const key = keyFn(item, i++)
      ;(out[key] ??= []).push(item)
    }
    return out
  }
}

export default withNuxt({
  rules: {
    // Single-word component names like `Modal` are intentional here.
    'vue/multi-word-component-names': 'off',
    // `any` is used intentionally for the generic API client and catch clauses;
    // surface it as a warning rather than a hard error.
    '@typescript-eslint/no-explicit-any': 'warn',
  },
})
