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
        clearTimeout(this._suppressTimeout)
    }

    jumpTo(event) {
        event.preventDefault()
        const id = event.currentTarget.getAttribute('href').slice(1)
        const target = document.getElementById(id)
        if (!target) return

        target.open = true
        requestAnimationFrame(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }))
        history.replaceState(null, '', '#' + id)
        this._setActiveLink(id)
        this._suppressObserverDuringScroll()

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

    // Le scroll programmé (smooth) traverse des sections intermédiaires qui
    // déclenchent l'observer avec de fausses entrées tant que l'animation
    // n'est pas terminée : on ignore ces mises à jour pendant ce court délai.
    _suppressObserverDuringScroll() {
        this._suppressed = true
        clearTimeout(this._suppressTimeout)
        const clear = () => { this._suppressed = false }
        this._suppressTimeout = setTimeout(clear, 1000)
        window.addEventListener('scrollend', clear, { once: true })
    }

    _onIntersect(entries) {
        if (this._suppressed) return
        entries.forEach(entry => {
            if (!entry.isIntersecting) return
            this._setActiveLink(entry.target.id)
        })
    }

    _setActiveLink(sectionId) {
        const link = this.tocLinkTargets.find(l => l.getAttribute('href') === '#' + sectionId)
        if (!link) return
        this.tocLinkTargets.forEach(l => { l.className = LINK_INACTIVE })
        link.className = LINK_ACTIVE
    }
}
