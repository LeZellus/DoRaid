import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['dialog']
  static values = { reopenHash: String }

  connect() {
    // Permet à une action (formulaire POST -> redirect) de rouvrir automatiquement
    // la popup après le rechargement de page, via un fragment d'URL dédié
    // (ex: data-modal-reopen-hash-value="groupes" + redirect vers "#groupes").
    if (this.reopenHashValue && window.location.hash === '#' + this.reopenHashValue) {
      this.open()
      history.replaceState(null, '', window.location.pathname + window.location.search)
    }
  }

  open() {
    this.dialogTarget.classList.remove('is-closing')
    this.dialogTarget.showModal()
  }

  close() {
    const dialog = this.dialogTarget
    dialog.classList.add('is-closing')
    dialog.addEventListener('animationend', () => {
      dialog.classList.remove('is-closing')
      dialog.close()
    }, { once: true })
  }

  clickBackdrop(event) {
    if (event.target === this.dialogTarget) {
      this.close()
    }
  }
}
