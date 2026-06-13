import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    connect() {
        const els = this.element.querySelectorAll('[data-reveal]')
        if (!els.length) return

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed')
                        observer.unobserve(entry.target)
                    }
                })
            },
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
        )

        els.forEach(el => observer.observe(el))
        this._observer = observer
    }

    disconnect() {
        this._observer?.disconnect()
    }
}
