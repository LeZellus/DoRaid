import { Controller } from '@hotwired/stimulus'

// Fait démarrer une liste (commentaires, chat...) scrollée sur son dernier élément,
// pour ne pas avoir à scroller manuellement pour voir le plus récent.
export default class extends Controller {
  connect() {
    this.element.scrollTop = this.element.scrollHeight
  }
}
