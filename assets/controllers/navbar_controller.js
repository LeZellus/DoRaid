import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['panel', 'openIcon', 'closeIcon', 'button', 'accountPanel', 'accountButton', 'accountChevron']

    connect() {
        this._onClickOutside = (event) => {
            if (!this.hasAccountPanelTarget || this.accountPanelTarget.classList.contains('hidden')) return
            if (this.accountPanelTarget.contains(event.target) || this.accountButtonTarget.contains(event.target)) return
            this.closeAccount()
        }
        this._onKeydown = (event) => {
            if (event.key === 'Escape') this.closeAccount()
        }
        document.addEventListener('click', this._onClickOutside)
        document.addEventListener('keydown', this._onKeydown)
    }

    disconnect() {
        document.removeEventListener('click', this._onClickOutside)
        document.removeEventListener('keydown', this._onKeydown)
    }

    toggle() {
        const willOpen = this.panelTarget.classList.contains('hidden')
        this.panelTarget.classList.toggle('hidden', !willOpen)
        this.openIconTarget.classList.toggle('hidden', willOpen)
        this.closeIconTarget.classList.toggle('hidden', !willOpen)
        this.buttonTarget.setAttribute('aria-expanded', willOpen ? 'true' : 'false')
    }

    close() {
        this.panelTarget.classList.add('hidden')
        this.openIconTarget.classList.remove('hidden')
        this.closeIconTarget.classList.add('hidden')
        this.buttonTarget.setAttribute('aria-expanded', 'false')
    }

    toggleAccount(event) {
        event.stopPropagation()
        const willOpen = this.accountPanelTarget.classList.contains('hidden')
        this.accountPanelTarget.classList.toggle('hidden', !willOpen)
        this.accountButtonTarget.setAttribute('aria-expanded', willOpen ? 'true' : 'false')
        this.accountChevronTarget.classList.toggle('rotate-180', willOpen)
    }

    closeAccount() {
        this.accountPanelTarget.classList.add('hidden')
        this.accountButtonTarget.setAttribute('aria-expanded', 'false')
        this.accountChevronTarget.classList.remove('rotate-180')
    }
}
