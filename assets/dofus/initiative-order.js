import { classRoles, roleWeights, roleConstraints, FALLBACK_ROLE } from './initiative-config.js'

export function getCandidateRoles(className) {
  return [...(classRoles[className] ?? [FALLBACK_ROLE])]
}

function resolveConstrainedRoles(team, candidatesByMember) {
  const assigned = new Map()

  for (const [role, { maxCount, priority }] of Object.entries(roleConstraints)) {
    const eligible = team.filter(m => candidatesByMember.get(m.id).includes(role))

    const sorted = [...eligible].sort((a, b) => {
      const rankA = priority.indexOf(a.class)
      const rankB = priority.indexOf(b.class)
      return (rankA === -1 ? Infinity : rankA) - (rankB === -1 ? Infinity : rankB)
    })

    const winners = sorted.slice(0, maxCount)
    const winnerIds = new Set(winners.map(m => m.id))
    winners.forEach(m => assigned.set(m.id, role))

    team.forEach(m => {
      if (!winnerIds.has(m.id)) {
        candidatesByMember.set(m.id, candidatesByMember.get(m.id).filter(r => r !== role))
      }
    })
  }

  return assigned
}

/**
 * Fonction pure : prend un tableau de personnages {id, name, class}, retourne le
 * même tableau enrichi de {role, weight} et trié par poids décroissant.
 */
export function buildInitiativeOrder(team) {
  const candidatesByMember = new Map(team.map(m => [m.id, getCandidateRoles(m.class)]))
  const constrained = resolveConstrainedRoles(team, candidatesByMember)

  const withRoles = team.map(m => {
    const role = constrained.get(m.id) ?? (candidatesByMember.get(m.id)[0] ?? FALLBACK_ROLE)
    return { ...m, role, weight: roleWeights[role] ?? 0 }
  })

  return withRoles.sort((a, b) => b.weight - a.weight)
}
