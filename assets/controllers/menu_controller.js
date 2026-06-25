import { Controller } from '@hotwired/stimulus'

// Menu d'actions (⋮) dont le panneau est sorti du flux (porté dans <body>, position fixed)
// pour ne pas être rogné par les conteneurs avec overflow (ex : listes de participants scrollables).
export default class extends Controller {
  static targets = ['panel']

  connect() {
    this._panel = this.panelTarget
    document.body.append(this._panel)
    this._open = false

    this._onClickOutside = e => {
      if (!this._open) return
      if (this.element.contains(e.target) || this._panel.contains(e.target)) return
      if (e.target.closest('[role="listbox"]')) return
      this.close()
    }
    this._onReposition = () => { if (this._open) this._position() }

    document.addEventListener('click', this._onClickOutside)
    window.addEventListener('scroll', this._onReposition, true)
    window.addEventListener('resize', this._onReposition)
  }

  disconnect() {
    document.removeEventListener('click', this._onClickOutside)
    window.removeEventListener('scroll', this._onReposition, true)
    window.removeEventListener('resize', this._onReposition)
    this._panel.remove()
  }

  toggle(event) {
    event.stopPropagation()
    this._open ? this.close() : this.open()
  }

  open() {
    this._position()
    this._panel.classList.remove('hidden')
    this._open = true
  }

  close() {
    this._panel.classList.add('hidden')
    this._open = false
  }

  _position() {
    const rect = this.element.getBoundingClientRect()
    this._panel.style.right = `${window.innerWidth - rect.right}px`

    const spaceBelow = window.innerHeight - rect.bottom
    if (spaceBelow < 320 && rect.top > spaceBelow) {
      this._panel.style.bottom = `${window.innerHeight - rect.top + 6}px`
      this._panel.style.top = ''
    } else {
      this._panel.style.top = `${rect.bottom + 6}px`
      this._panel.style.bottom = ''
    }
  }
}
