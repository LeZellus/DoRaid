/**
 * Fonctions pures de calcul du répartiteur de loot — même logique que
 * App\Service\RaidScoreCalculator (PHP), reproduite côté client pour permettre un
 * recalcul instantané pendant l'édition des probabilités/compositions (simulation
 * locale, sans aller-retour serveur).
 */

/** @param {{probs: number[]}} mob  probs en pourcentage (0-100), même ordre que gems */
export function expectedPointsForMob(mob, gems) {
  return mob.probs.reduce((sum, p, i) => sum + (p / 100) * gems[i].value, 0)
}

/** @param {{counts: Record<string, number>}} composition  quantités par nom de mob */
export function compositionScore(composition, mobsByName, gems) {
  let score = 0
  for (const [mobName, qty] of Object.entries(composition.counts)) {
    const mob = mobsByName.get(mobName)
    if (!mob || !qty) continue
    score += qty * expectedPointsForMob(mob, gems)
  }
  return score
}

/**
 * Assigne chaque groupe à la meilleure combinaison disponible (score > 0) pour
 * maximiser le score total : tri décroissant tailles/scores puis appariement rang à
 * rang. Chaque combinaison n'est utilisée que par un seul groupe.
 *
 * @param {number[]} groupSizes
 * @param {Array<{score: number, [k: string]: any}>} combos
 */
export function optimizeGroupAssignment(groupSizes, combos) {
  const scored = combos.filter(c => c.score > 0).sort((a, b) => b.score - a.score)
  const sizes = [...groupSizes].sort((a, b) => b - a)

  const assignments = []
  let totalScore = 0
  sizes.forEach((size, i) => {
    const combo = scored[i] ?? null
    const score = combo ? combo.score : 0
    const subtotal = size * score
    totalScore += subtotal
    assignments.push({ groupSize: size, combo, score, subtotal })
  })

  return { assignments, totalScore }
}
