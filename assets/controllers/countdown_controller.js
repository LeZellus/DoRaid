import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    connect() {
        this._tick()
        this._timer = setInterval(() => this._tick(), 1000)
    }

    disconnect() {
        clearInterval(this._timer)
    }

    _tick() {
        const now = Math.floor(Date.now() / 1000)
        this.element.querySelectorAll('[data-countdown]').forEach(el => {
            const target = parseInt(el.dataset.countdown, 10)
            const mode   = el.dataset.mode
            const diff   = target - now
            const textEl = el.querySelector('.countdown-text')
            if (!textEl) return

            if (mode === 'before') {
                textEl.textContent = this._formatBefore(diff)
            } else if (mode === 'after') {
                textEl.textContent = this._formatAfter(-diff)
                const endEl = el.querySelector('.countdown-end-text')
                if (endEl && el.dataset.end) {
                    endEl.textContent = this._formatEnd(parseInt(el.dataset.end, 10) - now)
                }
            }
        })
    }

    _pad(n) { return String(n).padStart(2, '0') }

    _formatBefore(sec) {
        if (sec <= 0) return 'Le raid vient de commencer !'
        const d = Math.floor(sec / 86400)
        const h = Math.floor((sec % 86400) / 3600)
        const m = Math.floor((sec % 3600) / 60)
        const s = sec % 60
        const parts = []
        if (d > 0) parts.push(d + ' jour' + (d > 1 ? 's' : ''))
        if (h > 0) parts.push(h + 'h')
        parts.push(this._pad(m) + 'min')
        parts.push(this._pad(s) + 's')
        return 'Le raid commence dans ' + parts.join(' ')
    }

    _formatAfter(elapsed) {
        const h = Math.floor(elapsed / 3600)
        const m = Math.floor((elapsed % 3600) / 60)
        if (h > 0 && m > 0) return 'Le raid a commencé il y a ' + h + 'h ' + m + 'min'
        if (h > 0) return 'Le raid a commencé il y a ' + h + ' heure' + (h > 1 ? 's' : '')
        if (m > 0) return 'Le raid a commencé il y a ' + m + ' minute' + (m > 1 ? 's' : '')
        return 'Le raid vient de commencer'
    }

    _formatEnd(sec) {
        if (sec > 0) {
            const h = Math.floor(sec / 3600)
            const m = Math.floor((sec % 3600) / 60)
            const s = sec % 60
            if (h > 0) return 'Fin dans ' + h + 'h ' + this._pad(m) + 'min'
            if (m > 0) return 'Fin dans ' + m + 'min ' + this._pad(s) + 's'
            return 'Fin dans ' + s + 's'
        }
        const over = -sec
        const h    = Math.floor(over / 3600)
        const m    = Math.floor((over % 3600) / 60)
        if (h > 0) return 'Dépassé de ' + h + 'h' + (m > 0 ? ' ' + m + 'min' : '')
        if (m > 0) return 'Dépassé de ' + m + 'min'
        return 'Durée dépassée'
    }
}
