// Config du calculateur de composition Gigalodon : ajouter une classe ou un rôle
// ne doit demander qu'une modification ici, jamais toucher à initiative-order.js.

export const classRoles = {
  SRAM: ['ERODEUR', 'DPS'],
  OUGINAK: ['ERODEUR', 'VULN', 'DPS'],
  IOP: ['ERODEUR', 'DPS'],
  ECAFLIP: ['ERODEUR', 'DPS'],
  ENIRIPSA: ['BOOSTER'],
  OSAMODAS: ['BOOSTER'],
  ENUTROF: ['SUPPORT'],
  FECA: ['SUPPORT'],
  PANDAWA: ['VULN'],
  CRA: ['CRA'],
  ELIOTROPE: ['PORTAIL'],
  FORGELANCE: ['VULN'],
  ZOBAL: ['VULN'],
  HUPPERMAGE: ['VULN'],
}

export const roleWeights = { ERODEUR: 100, PORTAIL: 90, VULN: 80, BOOSTER: 60, SUPPORT: 40, DPS: 20, CRA: 0 }

export const roleConstraints = {
  ERODEUR: { maxCount: 1, priority: ['SRAM', 'OUGINAK', 'ECAFLIP', 'IOP'] },
}

export const FALLBACK_ROLE = 'DPS'
