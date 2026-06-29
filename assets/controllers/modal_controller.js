import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['dialog']

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
