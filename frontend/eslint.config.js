import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
  { ignores: ['dist/**', 'node_modules/**'] },
  js.configs.recommended,
  ...vue.configs['flat/essential'],
  {
    languageOptions: { globals: globals.browser },
  },
  {
    files: ['*.config.js'],
    languageOptions: { globals: globals.node },
  },
]
