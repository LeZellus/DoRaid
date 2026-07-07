// Config du calculateur de composition Gigalodon : ajouter une classe ou un rôle
// ne doit demander qu'une modification ici, jamais toucher à initiative-order.js.

export const classRoles = {
  SRAM: ['ERODEUR', 'DPS'],
  OUGINAK: ['ERODEUR', 'DPS'],
  IOP: ['ERODEUR', 'DPS'],
  ECAFLIP: ['ERODEUR', 'DPS'],
  ENIRIPSA: ['BOOSTER'],
  OSAMODAS: ['BOOSTER'],
  ENUTROF: ['SUPPORT'],
  FECA: ['SUPPORT'],
  PANDAWA: ['SUPPORT'],
  CRA: ['CRA'],
}

export const roleWeights = { ERODEUR: 100, BOOSTER: 80, SUPPORT: 60, DPS: 40, CRA: 0 }

export const roleConstraints = {
  ERODEUR: { maxCount: 1, priority: ['SRAM', 'OUGINAK', 'ECAFLIP', 'IOP'] },
}

export const FALLBACK_ROLE = 'DPS'
