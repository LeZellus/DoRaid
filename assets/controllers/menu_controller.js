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
    // Le panneau doit être rendu visible AVANT de calculer sa position : caché
    // (display:none), ses dimensions réelles sont nulles et le calage ci-dessous
    // serait inopérant. Comme tout se joue en synchrone avant le prochain paint,
    // il n'y a pas de flash visuel d'un panneau mal placé puis repositionné.
    this.panelTarget.classList.remove('hidden')
    this._position()
    this._open = true
  }

  close() {
    this.panelTarget.classList.add('hidden')
    this._open = false
  }

  _position() {
    const rect = this.element.getBoundingClientRect()
    const margin = 8
    const panelWidth = this.panelTarget.offsetWidth

    // Ancré par défaut sur le bord droit du déclencheur, mais toujours contraint
    // dans les limites de l'écran — jamais de débordement horizontal, quelle que
    // soit la taille de l'écran.
    let left = rect.right - panelWidth
    left = Math.min(Math.max(left, margin), window.innerWidth - panelWidth - margin)
    this.panelTarget.style.left = `${left}px`
    this.panelTarget.style.right = ''

    const spaceBelow = window.innerHeight - rect.bottom
    const spaceAbove = rect.top
    const openUpward = spaceBelow < 320 && spaceAbove > spaceBelow

    // Hauteur maximale bornée à l'espace réellement disponible (au-dessus ou en
    // dessous selon le sens d'ouverture) : le panneau défile en interne plutôt que
    // de déborder de l'écran, quel que soit le nombre d'options qu'il contient.
    if (openUpward) {
      this.panelTarget.style.bottom = `${window.innerHeight - rect.top + 6}px`
      this.panelTarget.style.top = ''
      this.panelTarget.style.maxHeight = `${Math.max(spaceAbove - margin - 6, 120)}px`
    } else {
      this.panelTarget.style.top = `${rect.bottom + 6}px`
      this.panelTarget.style.bottom = ''
      this.panelTarget.style.maxHeight = `${Math.max(spaceBelow - margin - 6, 120)}px`
    }
  }
}
