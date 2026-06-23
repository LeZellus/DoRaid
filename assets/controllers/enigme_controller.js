import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['images', 'comments', 'commentInput', 'pasteZone', 'resolveBtn', 'resolvedBadge']
    static values  = { id: Number, token: String, updatedAt: String, resolved: Number }

    connect() {
        this.pasteHandler = this.handlePaste.bind(this)
        document.addEventListener('paste', this.pasteHandler)
        this.pollTimer = setInterval(() => this._poll(), 3000)
    }

    disconnect() {
        document.removeEventListener('paste', this.pasteHandler)
        clearInterval(this.pollTimer)
    }

    resolvedValueChanged(value) {
        if (this.hasResolveBtnTarget)    this.resolveBtnTarget.hidden    = value === 1
        if (this.hasResolvedBadgeTarget) this.resolvedBadgeTarget.hidden = value !== 1
    }

    focusPasteZone() {
        document.activeElement?.blur()
        this.pasteZoneTarget.focus()
    }

    handlePaste(event) {
        const tag = event.target?.tagName
        if (tag === 'INPUT' || tag === 'TEXTAREA') return

        const items     = Array.from(event.clipboardData?.items ?? [])
        const imageItem = items.find(i => i.type.startsWith('image/'))
        if (!imageItem) return

        event.preventDefault()

        const blob = imageItem.getAsFile()
        if (!blob) return

        this._setPasteZone('⏳ Compression…')

        this._compress(blob)
            .then(compressed => {
                const fd = new FormData()
                fd.append('image', compressed, `paste-${Date.now()}.jpg`)
                fd.append('_token', this.tokenValue)
                this._setPasteZone('⏳ Upload en cours…')
                return fetch(`/enigmes/${this.idValue}/images`, { method: 'POST', body: fd })
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (ok && data.success) {
                    this.imagesTarget.innerHTML = data.imagesHtml
                    this.updatedAtValue = data.updatedAt
                    this._resetPasteZone()
                } else {
                    const msg = data.error ?? 'Erreur inconnue'
                    console.error('[enigme] upload error:', msg)
                    this._setPasteZone('❌ ' + msg)
                    setTimeout(() => this._resetPasteZone(), 4000)
                }
            })
            .catch(err => {
                console.error('[enigme] upload failed:', err)
                this._setPasteZone('❌ Échec réseau')
                setTimeout(() => this._resetPasteZone(), 3000)
            })
    }

    submitComment(event) {
        event.preventDefault()
        const fd    = new FormData(event.target)
        const input = this.commentInputTarget
        if (!input.value.trim()) return

        input.disabled = true

        fetch(`/enigmes/${this.idValue}/comments`, { method: 'POST', body: fd })
            .then(r => {
                if (!r.ok) {
                    return r.text().then(text => {
                        console.error('[enigme] comment HTTP ' + r.status, text)
                        throw new Error('HTTP ' + r.status)
                    })
                }
                return r.json()
            })
            .then(data => {
                if (data.success) {
                    this.commentsTarget.innerHTML = data.commentsHtml
                    this.updatedAtValue = data.updatedAt
                    input.value = ''
                    this._scrollCommentsToBottom()
                } else {
                    console.error('[enigme] comment error:', data.error)
                    alert('Erreur : ' + (data.error ?? 'Impossible d\'envoyer le commentaire'))
                }
            })
            .catch(err => {
                console.error('[enigme] comment failed:', err)
                alert('Erreur réseau : ' + err.message)
            })
            .finally(() => { input.disabled = false })
    }

    resolve() {
        const fd = new FormData()
        fd.append('_token', this.tokenValue)

        fetch(`/enigmes/${this.idValue}/resolve`, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.text().then(t => { throw new Error(t) }))
            .then(data => {
                if (data.success) {
                    this.resolvedValue  = data.resolved ? 1 : 0
                    this.updatedAtValue = data.updatedAt
                }
            })
            .catch(err => console.error('[enigme] resolve failed:', err))
    }

    _poll() {
        fetch(`/enigmes/${this.idValue}/partial?since=${encodeURIComponent(this.updatedAtValue)}`)
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data?.changed) return
                // Ne resnap en bas que si l'utilisateur y était déjà, pour ne pas l'interrompre
                // s'il est en train de relire d'anciens commentaires pendant le polling.
                const c = this.commentsTarget
                const wasNearBottom = c.scrollHeight - c.scrollTop - c.clientHeight < 40

                this.imagesTarget.innerHTML   = data.imagesHtml
                this.commentsTarget.innerHTML = data.commentsHtml
                this.resolvedValue            = data.resolved ? 1 : 0
                this.updatedAtValue           = data.updatedAt
                if (wasNearBottom) this._scrollCommentsToBottom()
            })
            .catch(() => {})
    }

    _scrollCommentsToBottom() {
        this.commentsTarget.scrollTop = this.commentsTarget.scrollHeight
    }

    _compress(blob, maxPx = 1920, quality = 0.85) {
        return new Promise(resolve => {
            const url = URL.createObjectURL(blob)
            const img = new Image()
            img.onload = () => {
                URL.revokeObjectURL(url)
                let { naturalWidth: w, naturalHeight: h } = img
                if (w > maxPx || h > maxPx) {
                    const ratio = Math.min(maxPx / w, maxPx / h)
                    w = Math.round(w * ratio)
                    h = Math.round(h * ratio)
                }
                const canvas = document.createElement('canvas')
                canvas.width  = w
                canvas.height = h
                canvas.getContext('2d').drawImage(img, 0, 0, w, h)
                canvas.toBlob(out => resolve(out ?? blob), 'image/jpeg', quality)
            }
            img.onerror = () => { URL.revokeObjectURL(url); resolve(blob) }
            img.src = url
        })
    }

    _setPasteZone(text) {
        this.pasteZoneTarget.textContent = text
    }

    _resetPasteZone() {
        this.pasteZoneTarget.innerHTML =
            '<span class="block text-2xl mb-1">📋</span>Cliquer ici puis coller une image (Ctrl+V)'
    }
}
