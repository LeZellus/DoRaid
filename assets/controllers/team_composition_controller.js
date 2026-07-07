import { Controller } from '@hotwired/stimulus'
import { buildInitiativeOrder } from '../dofus/initiative-order.js'

const ROLE_BADGE = {
  ERODEUR: 'bg-red-900/50 text-red-400 border border-red-800/40',
  BOOSTER: 'bg-emerald-900/50 text-emerald-300 border border-emerald-700/40',
  SUPPORT: 'bg-blue-900/50 text-blue-400 border border-blue-800/40',
  DPS: 'bg-gray-700 text-gray-300',
  CRA: 'bg-pink-900/50 text-pink-400 border border-pink-800/40',
}

// Simulation client-side de composition Gigalodon : permet d'inclure/exclure des
// participants réellement inscrits au raid sans jamais toucher aux données du raid.
export default class extends Controller {
  static targets = ['chip', 'result']
  static values = { participants: Array }

  connect() {
    this.excluded = new Set()
    this.render()
  }

  toggle(event) {
    const id = Number(event.currentTarget.dataset.id)
    this.excluded.has(id) ? this.excluded.delete(id) : this.excluded.add(id)
    this.render()
  }

  render() {
    this.chipTargets.forEach(chip => {
      const excluded = this.excluded.has(Number(chip.dataset.id))
      chip.classList.toggle('opacity-40', excluded)
      chip.classList.toggle('border-gray-700', excluded)
      chip.classList.toggle('border-emerald-700/50', !excluded)
    })

    const team = this.participantsValue.filter(p => !this.excluded.has(p.id))
    const ordered = buildInitiativeOrder(team)

    this.resultTarget.innerHTML = ordered.length === 0
      ? '<li class="px-3 py-4 text-center text-sm text-gray-600">Aucun participant sélectionné.</li>'
      : ordered.map((m, idx) => `
        <li class="flex items-center justify-between gap-3 px-3 py-2 bg-gray-800/30">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="text-xs text-gray-600 w-5 text-right shrink-0 tabular-nums">${idx + 1}</span>
            <img src="${m.icon}" alt="${m.class}" class="w-5 h-5 rounded-full object-cover shrink-0">
            <span class="text-sm text-gray-200 truncate">${m.name}</span>
          </div>
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${ROLE_BADGE[m.role] ?? ROLE_BADGE.DPS}">
            ${m.role} · ${m.weight}
          </span>
        </li>
      `).join('')
  }
}
