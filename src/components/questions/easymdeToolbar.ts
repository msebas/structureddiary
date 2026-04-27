import EasyMDE from 'easymde'
import {
  mdiCheckboxBlankOutline,
  mdiEyeOutline,
  mdiFormatBold,
  mdiFormatStrikethroughVariant,
  mdiFormatHeaderPound,
  mdiFormatItalic,
  mdiFormatListBulleted,
  mdiFormatListNumbered,
  mdiFormatQuoteClose,
  mdiHelpCircleOutline,
  mdiLinkVariant,
} from '@mdi/js'

type ToolbarItem = {
  name: string
  action: ((editor: EasyMDE) => void) | string
  className?: string
  noDisable?: boolean
  noMobile?: boolean
  title: string
}

function svgDataUri(path: string): string {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="black" d="${path}"/></svg>`
  return `url("data:image/svg+xml,${encodeURIComponent(svg)}")`
}

const toolbarIcons = {
  bold: svgDataUri(mdiFormatBold),
  italic: svgDataUri(mdiFormatItalic),
  strike: svgDataUri(mdiFormatStrikethroughVariant),
  heading: svgDataUri(mdiFormatHeaderPound),
  quote: svgDataUri(mdiFormatQuoteClose),
  unorderedList: svgDataUri(mdiFormatListBulleted),
  orderedList: svgDataUri(mdiFormatListNumbered),
  link: svgDataUri(mdiLinkVariant),
  preview: svgDataUri(mdiEyeOutline),
  guide: svgDataUri(mdiHelpCircleOutline),
  checkboxList: svgDataUri(mdiCheckboxBlankOutline),
}

function insertCheckboxList(editor: EasyMDE): void {
  const cm = editor.codemirror
  const selection = cm.getSelection()

  if (selection.trim() === '') {
    cm.replaceSelection('* [ ] ')
    cm.focus()
    return
  }

  const prefixed = selection
    .split('\n')
    .map((line) => line.trim() === '' ? '* [ ] ' : `* [ ] ${line}`)
    .join('\n')

  cm.replaceSelection(prefixed)
  cm.focus()
}

export const questionEditorToolbar: Array<ToolbarItem | '|'> = [
  {
    name: 'bold',
    action: EasyMDE.toggleBold,
    className: 'mde-mdi mde-mdi-bold',
    title: 'Bold',
  },
  {
    name: 'italic',
    action: EasyMDE.toggleItalic,
    className: 'mde-mdi mde-mdi-italic',
    title: 'Italic',
  },
  {
    name: 'strikethrough',
    action: EasyMDE.toggleStrikethrough,
    className: 'mde-mdi mde-mdi-strike',
    title: 'Strikethrough',
  },
  {
    name: 'heading',
    action: EasyMDE.toggleHeadingSmaller,
    className: 'mde-mdi mde-mdi-heading',
    title: 'Heading',
  },
  '|',
  {
    name: 'quote',
    action: EasyMDE.toggleBlockquote,
    className: 'mde-mdi mde-mdi-quote',
    title: 'Quote',
  },
  {
    name: 'unordered-list',
    action: EasyMDE.toggleUnorderedList,
    className: 'mde-mdi mde-mdi-unordered-list',
    title: 'Generic List',
  },
  {
    name: 'ordered-list',
    action: EasyMDE.toggleOrderedList,
    className: 'mde-mdi mde-mdi-ordered-list',
    title: 'Numbered List',
  },
  {
    name: 'checkbox-list',
    action: insertCheckboxList,
    className: 'mde-mdi mde-mdi-checkbox-list',
    title: 'Insert Checkbox List',
  },
  '|',
  {
    name: 'link',
    action: EasyMDE.drawLink,
    className: 'mde-mdi mde-mdi-link',
    title: 'Create Link',
  },
  {
    name: 'preview',
    action: EasyMDE.togglePreview,
    className: 'mde-mdi mde-mdi-preview',
    noDisable: true,
    title: 'Toggle Preview',
  },
  {
    name: 'guide',
    action: 'https://www.markdownguide.org/basic-syntax/',
    className: 'mde-mdi mde-mdi-guide',
    noDisable: true,
    title: 'Markdown Guide',
  },
]

let toolbarStylesInstalled = false

export function ensureQuestionEditorToolbarStyles(): void {
  if (toolbarStylesInstalled || typeof document === 'undefined') {
    return
  }

  const style = document.createElement('style')
  style.dataset.structuredDiaryMdiToolbar = 'true'
  style.textContent = `
.editor-toolbar button.mde-mdi {
  position: relative;
}

.editor-toolbar button.mde-mdi i {
  display: none;
}

.editor-toolbar button.mde-mdi::before {
  content: '';
  display: block;
  inline-size: 18px;
  block-size: 18px;
  margin: 0 auto;
  background-color: currentColor;
  mask-image: var(--mde-mdi-icon);
  mask-repeat: no-repeat;
  mask-position: center;
  mask-size: contain;
  -webkit-mask-image: var(--mde-mdi-icon);
  -webkit-mask-repeat: no-repeat;
  -webkit-mask-position: center;
  -webkit-mask-size: contain;
}

.editor-toolbar button.mde-mdi-bold { --mde-mdi-icon: ${toolbarIcons.bold}; }
.editor-toolbar button.mde-mdi-italic { --mde-mdi-icon: ${toolbarIcons.italic}; }
.editor-toolbar button.mde-mdi-strike { --mde-mdi-icon: ${toolbarIcons.strike}; }
.editor-toolbar button.mde-mdi-heading { --mde-mdi-icon: ${toolbarIcons.heading}; }
.editor-toolbar button.mde-mdi-quote { --mde-mdi-icon: ${toolbarIcons.quote}; }
.editor-toolbar button.mde-mdi-unordered-list { --mde-mdi-icon: ${toolbarIcons.unorderedList}; }
.editor-toolbar button.mde-mdi-ordered-list { --mde-mdi-icon: ${toolbarIcons.orderedList}; }
.editor-toolbar button.mde-mdi-link { --mde-mdi-icon: ${toolbarIcons.link}; }
.editor-toolbar button.mde-mdi-checkbox-list { --mde-mdi-icon: ${toolbarIcons.checkboxList}; }
.editor-toolbar button.mde-mdi-preview { --mde-mdi-icon: ${toolbarIcons.preview}; }
.editor-toolbar button.mde-mdi-guide { --mde-mdi-icon: ${toolbarIcons.guide}; }
`

  document.head.appendChild(style)
  toolbarStylesInstalled = true
}
