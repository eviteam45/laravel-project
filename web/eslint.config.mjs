import withNuxt from './.nuxt/eslint.config.mjs'

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

    'vue/multi-word-component-names': 'off',

    '@typescript-eslint/no-explicit-any': 'warn',
  },
})
