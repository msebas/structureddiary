import type EasyMDE from 'easymde'
import type { DefineComponent, Plugin } from 'vue'
import type { Options } from 'easymde'

declare module 'vue3-easymde' {
  export interface EditorProps {
    modelValue?: string
    options?: Options
  }

  export interface EditorEvents {
    (type: 'update:modelValue', value: string): void
    (type: 'change', value: string): void
    (type: 'blur'): void
  }

  export interface EditorInstance {
    clear: () => void
    getMDEInstance: () => EasyMDE | null
  }

  export const VueEasyMDE: DefineComponent<EditorProps>

  const plugin: Plugin
  export default plugin
}
