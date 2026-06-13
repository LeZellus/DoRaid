import { Controller } from '@hotwired/stimulus'

// Classes complètes pour que Tailwind v4 les détecte au scan
const ACTIVE   = 'px-4 py-2.5 text-sm cursor-pointer transition-colors text-indigo-400 bg-indigo-600/10'
const INACTIVE = 'px-4 py-2.5 text-sm cursor-pointer transition-colors text-gray-300 hover:text-white hover:bg-gray-700/50'

export default class extends Controller {
  connect () {
    const native = this.element.querySelector('select')
    if (!native) return
    this._native = native
    native.hidden = true
    this._buildTrigger()
    this._buildList()
    this._outside = e => { if (!this.element.contains(e.target)) this._close() }
    document.addEventListener('click', this._outside)
  }

  disconnect () {
    document.removeEventListener('click', this._outside)
  }

  _buildTrigger () {
    const btn = this._btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'w-full flex items-center justify-between gap-3 bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-sm text-left transition hover:border-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer'
    btn.setAttribute('aria-haspopup', 'listbox')
    btn.setAttribute('aria-expanded', 'false')

    const lbl = this._lbl = document.createElement('span')
    lbl.className = 'truncate'
    const sel = this._native
    const opt = sel.options[sel.selectedIndex]
    lbl.textContent = opt ? opt.text : '—'
    lbl.classList.add(!opt || opt.value === '' ? 'text-gray-500' : 'text-white')

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
    svg.setAttribute('viewBox', '0 0 20 20')
    svg.setAttribute('fill', 'currentColor')
    svg.classList.add('w-4', 'h-4', 'text-gray-500', 'shrink-0', 'transition-transform', 'duration-200')
    svg.innerHTML = '<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>'
    this._svg = svg

    btn.append(lbl, svg)
    btn.addEventListener('click', e => { e.stopPropagation(); this._toggle() })
    btn.addEventListener('keydown', e => { if (e.key === 'Escape') this._close() })
    this.element.append(btn)
  }

  _buildList () {
    const ul = this._list = document.createElement('ul')
    ul.setAttribute('role', 'listbox')
    ul.hidden = true
    ul.className = 'absolute z-50 left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-xl shadow-2xl overflow-y-auto max-h-60 py-1'

    Array.from(this._native.options).forEach(opt => {
      const li = document.createElement('li')
      li.setAttribute('role', 'option')
      li.className = opt.value === this._native.value ? ACTIVE : INACTIVE
      li.textContent = opt.text
      li.dataset.value = opt.value
      li.addEventListener('click', () => this._pick(opt.value, opt.text))
      ul.append(li)
    })

    this.element.append(ul)
  }

  _pick (value, text) {
    this._native.value = value
    this._native.dispatchEvent(new Event('change', { bubbles: true }))
    this._lbl.textContent = text
    this._lbl.className = `truncate ${value === '' ? 'text-gray-500' : 'text-white'}`
    Array.from(this._list.children).forEach(li => {
      li.className = li.dataset.value === value ? ACTIVE : INACTIVE
    })
    this._close()
  }

  _toggle () { this._list.hidden ? this._open() : this._close() }

  _open () {
    this._list.hidden = false
    this._svg.classList.add('rotate-180')
    this._btn.setAttribute('aria-expanded', 'true')
  }

  _close () {
    this._list.hidden = true
    this._svg.classList.remove('rotate-180')
    this._btn.setAttribute('aria-expanded', 'false')
  }
}
