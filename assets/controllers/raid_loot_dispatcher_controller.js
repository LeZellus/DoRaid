import { Controller } from '@hotwired/stimulus'
import { expectedPointsForMob, compositionScore, optimizeGroupAssignment } from '../dofus/raid-loot-calculator.js'

const DOT_COLORS = ['#cfd3da', '#8fc4c9', '#5fae87', '#7a9c4f', '#4b6fb0', '#3a3450', '#c9a04d']

// Couleur propre à chaque gemme (reprise du prototype) — sert à identifier chaque
// palier d'un coup d'œil dans le tableau des probabilités de drop.
const GEM_COLORS_BY_NAME = {
  Quartz: '#cfd3da',
  Opale: '#8fc4c9',
  Amazonite: '#5fae87',
  Aventurine: '#7a9c4f',
  Lapiz: '#4b6fb0',
  Jais: '#8b7fc7',
  Onyx: '#c9a04d',
}
const gemColor = name => GEM_COLORS_BY_NAME[name] ?? '#6b7280'

function hexToRgba(hex, alpha) {
  const n = parseInt(hex.slice(1), 16)
  const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

// Simulateur client-side du répartiteur de loot : toutes les probabilités/quantités
// sont éditables et tout se recalcule instantanément (sans aller-retour serveur), à
// l'image du prototype public/uploads/raid_optimizer.html. Rien n'est jamais persisté
// ici — pour modifier les données de référence, voir l'admin (Gemmes/Mobs/Salles).
export default class extends Controller {
  static targets = [
    'mobTable', 'sallesContainer', 'bestComboBox',
    'totalPlayers', 'nbGroups', 'groupSizes', 'groupSizesSum', 'resultBox',
  ]
  static values = { gems: Array, mobs: Array, salles: Array }

  connect() {
    // Copies de travail locales : les *Value de Stimulus reparsent le JSON du DOM à
    // chaque accès, une mutation directe ne persisterait donc pas d'un rendu à l'autre.
    this.gems = this.gemsValue
    this.mobs = this.mobsValue.map(m => ({ ...m, probs: [...m.probs] }))
    this.salles = this.sallesValue.map(s => ({
      ...s,
      compositions: s.compositions.map(c => ({ ...c, counts: { ...c.counts } })),
    }))
    // Une fois qu'un premier calcul a été lancé, les modifications suivantes le
    // relancent automatiquement — évite d'afficher un résultat obsolète en silence.
    this.hasOptimized = false

    this.renderMobTable()
    this.renderSalles()
    this.renderGroupSizes()
  }

  fmt(n) {
    return (Math.round(n * 100) / 100).toString().replace('.', ',')
  }

  mobsByName() {
    return new Map(this.mobs.map(m => [m.name, m]))
  }

  reoptimizeIfNeeded() {
    if (this.hasOptimized) this.optimize()
  }

  // ─── Cartes des probabilités de drop (une par mob) ─────────────────────────

  renderMobTable() {
    const gems = this.gems

    this.mobTableTarget.innerHTML = this.mobs.map((mob, mi) => {
      const mobDot = DOT_COLORS[mi % DOT_COLORS.length]

      const cells = gems.map((gem, gi) => {
        const accent = gemColor(gem.name)
        return `
          <div class="rounded-lg bg-gray-800/60 border border-gray-700/60 p-2 flex flex-col items-center gap-1.5 text-center">
            <span class="inline-flex items-center gap-1 text-[10px] font-medium leading-tight" style="color:${accent}">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:${accent}"></span>
              ${gem.name}
            </span>
            <span class="inline-flex items-center gap-1 bg-gray-900/70 border border-gray-700 rounded-md px-1.5 py-1 transition
                          focus-within:ring-1 focus-within:ring-[var(--gem-accent)] focus-within:border-[var(--gem-accent)]"
                  style="--gem-accent:${accent}">
              <input type="number" step="0.1" value="${mob.probs[gi]}" data-mob-index="${mi}" data-gem-index="${gi}"
                     data-action="input->raid-loot-dispatcher#onProbChange"
                     class="w-9 bg-transparent text-xs text-center text-white focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
              <span class="text-[9px] text-gray-500">%</span>
            </span>
          </div>`
      }).join('')

      return `
        <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-3">
          <div class="flex items-center justify-between gap-2 mb-2.5">
            <span class="inline-flex items-center gap-2 text-sm font-medium text-gray-200 truncate">
              <span class="w-2 h-2 rounded-full shrink-0" style="background:${mobDot}"></span>
              ${mob.name}
            </span>
            <span class="shrink-0 font-mono font-semibold text-emerald-300 bg-emerald-900/30 rounded-full px-2.5 py-1 text-xs tabular-nums" data-mob-avg-index="${mi}">${this.fmt(expectedPointsForMob(mob, gems))}</span>
          </div>
          <div class="grid grid-cols-4 gap-1.5">${cells}</div>
        </div>`
    }).join('')
  }

  onProbChange(event) {
    const mi = Number(event.target.dataset.mobIndex)
    const gi = Number(event.target.dataset.gemIndex)
    this.mobs[mi].probs[gi] = parseFloat(event.target.value) || 0

    const avgCell = this.mobTableTarget.querySelector(`[data-mob-avg-index="${mi}"]`)
    if (avgCell) avgCell.textContent = this.fmt(expectedPointsForMob(this.mobs[mi], this.gems))

    this.renderSalles()
    this.reoptimizeIfNeeded()
  }

  // ─── Cartes de salles ───────────────────────────────────────────────────────

  renderSalles() {
    const mobsByName = this.mobsByName()
    const container = this.sallesContainerTarget
    container.innerHTML = ''

    let best = null // { salle, composition, score }

    this.salles.forEach((salle, si) => {
      const card = document.createElement('div')
      card.className = 'rounded-2xl border border-gray-800 bg-gray-900 overflow-hidden mb-4'

      const head = document.createElement('div')
      head.className = 'flex items-center justify-between px-5 py-3 border-b border-gray-800 bg-gray-800/40'
      head.innerHTML = `<h3 class="font-semibold text-white">${salle.name}</h3><span class="text-xs font-mono text-gray-500">niveau ${salle.range}</span>`
      card.appendChild(head)

      const tiers = document.createElement('div')
      tiers.className = 'grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-800'

      const scores = salle.compositions.map(composition => compositionScore(composition, mobsByName, this.gems))
      const maxScore = Math.max(...scores, 0)

      salle.compositions.forEach((composition, ci) => {
        const score = scores[ci]
        const isBest = score > 0 && score === maxScore
        if (score > 0 && (!best || score > best.score)) {
          best = { salle, composition, score }
        }

        const tierDiv = document.createElement('div')
        tierDiv.className = 'p-4 relative' + (isBest ? ' bg-emerald-950/20' : '')

        let rows = ''
        this.mobs.forEach((mob, mi) => {
          const color = DOT_COLORS[mi % DOT_COLORS.length]
          const qty = composition.counts[mob.name] || 0
          rows += `<div class="flex items-center justify-between text-xs text-gray-400 py-0.5">
            <span class="flex items-center gap-1.5 truncate"><span class="w-2 h-2 rounded-full shrink-0" style="background:${color}"></span>${mob.name}</span>
            <input type="number" min="0" value="${qty}" data-salle-index="${si}" data-composition-index="${ci}" data-mob-name="${mob.name}"
                   data-action="input->raid-loot-dispatcher#onCountChange"
                   class="w-12 bg-gray-800 border border-gray-700 rounded px-1 py-0.5 text-xs text-center text-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
          </div>`
        })

        tierDiv.innerHTML = `
          <div class="flex items-center gap-1.5 mb-1">
            <span class="text-[10px] uppercase tracking-wide text-gray-500">${composition.label}</span>
            ${isBest ? '<span class="text-[9px] font-semibold text-emerald-400 bg-emerald-900/40 border border-emerald-700/40 rounded-full px-1.5 py-0.5 leading-none">★ Meilleure</span>' : ''}
          </div>
          <div class="font-mono font-bold text-lg ${isBest ? 'text-emerald-400' : 'text-gray-300'} mb-0.5" data-composition-score="${si}-${ci}">${this.fmt(score)}</div>
          <div class="text-[10px] text-gray-600 mb-2">score espéré / joueur</div>
          ${rows}
        `
        tiers.appendChild(tierDiv)
      })

      card.appendChild(tiers)
      container.appendChild(card)
    })

    this.renderBestCombo(best)
  }

  renderBestCombo(best) {
    if (!best) {
      this.bestComboBoxTarget.innerHTML = '<p class="text-sm text-gray-500">Aucune composition ne rapporte de score pour l\'instant.</p>'
      return
    }

    this.bestComboBoxTarget.innerHTML = `
      <div>
        <span class="text-[10px] uppercase tracking-wide text-emerald-500">Meilleure combinaison actuelle</span>
        <p class="text-sm text-white mt-0.5">${best.salle.name} (${best.salle.range}) — ${best.composition.label}</p>
      </div>
      <span class="font-mono font-bold text-xl text-emerald-400 shrink-0">${this.fmt(best.score)}<span class="text-xs text-gray-500 font-sans font-normal ml-1">pts/j</span></span>
    `
  }

  onCountChange(event) {
    const si = Number(event.target.dataset.salleIndex)
    const ci = Number(event.target.dataset.compositionIndex)
    const mobName = event.target.dataset.mobName
    this.salles[si].compositions[ci].counts[mobName] = parseInt(event.target.value, 10) || 0

    // Une quantité modifiée peut changer la composition "meilleure" de la salle (mise en
    // avant) et la meilleure combinaison globale : on refait un rendu complet des salles
    // plutôt que de ne mettre à jour que la cellule de score.
    this.renderSalles()
    this.reoptimizeIfNeeded()
  }

  // ─── Tailles de groupe ──────────────────────────────────────────────────────

  renderGroupSizes() {
    const total = parseInt(this.totalPlayersTarget.value, 10) || 0
    const n = Math.max(1, parseInt(this.nbGroupsTarget.value, 10) || 1)

    // Répartition égale, le reste distribué aux premiers groupes.
    const base = Math.floor(total / n)
    const remainder = total % n
    const sizes = Array.from({ length: n }, (_, i) => base + (i < remainder ? 1 : 0))

    this.groupSizesTarget.innerHTML = sizes.map((size, i) => `
      <div class="text-center">
        <span class="block text-[10px] font-mono text-gray-500 mb-1">Grp ${i + 1}</span>
        <input type="number" min="0" value="${size}"
               data-action="input->raid-loot-dispatcher#onGroupSizeChange"
               class="w-full bg-gray-800 border border-gray-700 rounded px-1 py-1.5 text-sm text-center text-white focus:outline-none focus:ring-1 focus:ring-emerald-500" data-group-size-input>
      </div>
    `).join('')

    this.updateGroupSizesSum()
    this.reoptimizeIfNeeded()
  }

  onGroupSizeChange() {
    this.updateGroupSizesSum()
    this.reoptimizeIfNeeded()
  }

  updateGroupSizesSum() {
    const total = parseInt(this.totalPlayersTarget.value, 10) || 0
    const sum = Array.from(this.groupSizesTarget.querySelectorAll('[data-group-size-input]'))
      .reduce((acc, input) => acc + (parseInt(input.value, 10) || 0), 0)

    const matches = sum === total
    this.groupSizesSumTarget.textContent = `${sum} / ${total} joueurs`
    this.groupSizesSumTarget.className = 'text-xs font-mono ' + (matches ? 'text-emerald-400' : 'text-amber-400')
  }

  // ─── Optimisation ───────────────────────────────────────────────────────────

  optimize() {
    this.hasOptimized = true

    const mobsByName = this.mobsByName()
    const groupSizes = Array.from(this.groupSizesTarget.querySelectorAll('[data-group-size-input]'))
      .map(input => parseInt(input.value, 10) || 0)
      .filter(n => n > 0)

    const combos = []
    this.salles.forEach(salle => {
      salle.compositions.forEach(composition => {
        combos.push({
          salleName: salle.name,
          salleRange: salle.range,
          label: composition.label,
          score: compositionScore(composition, mobsByName, this.gems),
        })
      })
    })

    if (combos.every(c => c.score <= 0) || groupSizes.length === 0) {
      this.resultBoxTarget.innerHTML = '<p class="text-gray-500 text-sm text-center py-5">Aucune composition disponible ou aucune taille de groupe renseignée.</p>'
      return
    }

    const { assignments, totalScore } = optimizeGroupAssignment(groupSizes, combos)

    const rows = assignments.map(a => `
      <li class="flex items-center justify-between gap-3 px-3 py-2 bg-gray-800/30">
        <span class="text-sm text-gray-300">Groupe de ${a.groupSize} joueur${a.groupSize > 1 ? 's' : ''}</span>
        ${a.combo
          ? `<span class="text-sm text-white text-right">
              ${a.combo.salleName} (${a.combo.salleRange}) — ${a.combo.label}
              <span class="block text-xs text-gray-500">${this.fmt(a.score)} pts/joueur · ${this.fmt(a.subtotal)} pts au total</span>
            </span>`
          : '<span class="text-sm text-gray-600">Aucune combinaison disponible</span>'}
      </li>
    `).join('')

    this.resultBoxTarget.innerHTML = `
      <ul class="divide-y divide-gray-800/60 rounded-xl border border-gray-700 overflow-hidden mb-3">${rows}</ul>
      <p class="text-sm text-right text-emerald-400 font-semibold">Score total espéré : ${this.fmt(totalScore)} pts</p>
    `
  }
}
