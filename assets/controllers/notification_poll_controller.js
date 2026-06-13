import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['badge']
    static values  = { url: String }

    connect() {
        this._tick()
        this._timer = setInterval(() => this._tick(), 30000)
    }

    disconnect() {
        clearInterval(this._timer)
    }

    async _tick() {
        try {
            const r = await fetch(this.urlValue, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            if (!r.ok) return
            const { count } = await r.json()
            this._update(count)
        } catch {}
    }

    _update(count) {
        const badge = this.badgeTarget
        badge.textContent = count > 9 ? '9+' : String(count)

        if (count > 0) {
            badge.classList.remove('hidden')
            this.element.classList.remove('text-gray-400', 'hover:text-white', 'hover:bg-gray-800/60')
            this.element.classList.add('text-indigo-300', 'hover:text-indigo-200', 'hover:bg-indigo-900/20', 'bell-active')
        } else {
            badge.classList.add('hidden')
            this.element.classList.remove('text-indigo-300', 'hover:text-indigo-200', 'hover:bg-indigo-900/20', 'bell-active')
            this.element.classList.add('text-gray-400', 'hover:text-white', 'hover:bg-gray-800/60')
        }
    }
}
