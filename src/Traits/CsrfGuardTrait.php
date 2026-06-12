<?php

namespace App\Traits;

use Symfony\Component\HttpFoundation\Request;

trait CsrfGuardTrait
{
    private function requireCsrfToken(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }
    }
}
