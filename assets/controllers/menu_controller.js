import { Controller } from '@hotwired/stimulus'

// Menu d'actions (⋮). Le panneau reste à sa place dans le DOM — passer en position
// fixed suffit à échapper au overflow des listes scrollables, pas besoin de le
// déplacer dans <body> (ça casse la restauration de page de Turbo : le contrôleur
// "select" imbriqué se reconnecte sur un nœud déjà déplacé et finit orphelin).
export default class extends Controller {
  static targets = ['panel']

  connect() {
    this._open = false

    this._onClickOutside = e => {
      if (!this._open) return
      if (this.element.contains(e.target)) return
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
  }

  toggle(event) {
    event.stopPropagation()
    this._open ? this.close() : this.open()
  }

  open() {
    this._position()
    this.panelTarget.classList.remove('hidden')
    this._open = true
  }

  close() {
    this.panelTarget.classList.add('hidden')
    this._open = false
  }

  _position() {
    const rect = this.element.getBoundingClientRect()
    this.panelTarget.style.right = `${window.innerWidth - rect.right}px`

    const spaceBelow = window.innerHeight - rect.bottom
    if (spaceBelow < 320 && rect.top > spaceBelow) {
      this.panelTarget.style.bottom = `${window.innerHeight - rect.top + 6}px`
      this.panelTarget.style.top = ''
    } else {
      this.panelTarget.style.top = `${rect.bottom + 6}px`
      this.panelTarget.style.bottom = ''
    }
  }
}
