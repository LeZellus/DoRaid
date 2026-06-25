<?php

namespace App\Traits;

use App\Entity\Character;
use App\Repository\CharacterRepository;
use Symfony\Component\HttpFoundation\Request;

trait CharacterOwnershipTrait
{
    /** Résout un personnage depuis le POST et vérifie qu'il appartient à l'utilisateur courant. */
    private function resolveOwnCharacter(Request $request, CharacterRepository $repo, string $field = 'character_id'): ?Character
    {
        $id        = (int) $request->request->get($field);
        $character = $id > 0 ? $repo->find($id) : null;

        return $character?->belongsTo($this->getUser()) ? $character : null;
    }
}
