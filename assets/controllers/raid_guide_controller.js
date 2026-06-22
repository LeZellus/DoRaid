import { Controller } from '@hotwired/stimulus'

// Classes complètes pour que Tailwind v4 les détecte au scan
const LINK_ACTIVE   = 'block px-3 py-2 rounded-lg text-sm transition truncate bg-indigo-900/30 text-indigo-300 border-l-2 border-indigo-500'
const LINK_INACTIVE = 'block px-3 py-2 rounded-lg text-sm transition truncate text-gray-400 hover:text-white hover:bg-gray-800/60 border-l-2 border-transparent'

export default class extends Controller {
    static targets = ['section', 'tocLink', 'mobileToc']

    connect() {
        this._observer = new IntersectionObserver(
            (entries) => this._onIntersect(entries),
            { rootMargin: '-96px 0px -70% 0px', threshold: 0 }
        )
        this.sectionTargets.forEach(section => this._observer.observe(section))

        const hash = window.location.hash.slice(1)
        if (hash) {
            const target = document.getElementById(hash)
            if (target && this.sectionTargets.includes(target)) {
                target.open = true
                requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }))
            }
        }
    }

    disconnect() {
        this._observer?.disconnect()
    }

    jumpTo(event) {
        event.preventDefault()
        const id = event.currentTarget.getAttribute('href').slice(1)
        const target = document.getElementById(id)
        if (!target) return

        target.open = true
        requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }))
        history.replaceState(null, '', '#' + id)

        if (this.hasMobileTocTarget) {
            this.mobileTocTarget.open = false
        }
    }

    expandAll() {
        this.sectionTargets.forEach(section => { section.open = true })
    }

    collapseAll() {
        this.sectionTargets.forEach(section => { section.open = false })
    }

    _onIntersect(entries) {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return
            const link = this.tocLinkTargets.find(l => l.getAttribute('href') === '#' + entry.target.id)
            if (!link) return
            this.tocLinkTargets.forEach(l => { l.className = LINK_INACTIVE })
            link.className = LINK_ACTIVE
        })
    }
}
