<?php

namespace App\Traits;

use App\Exception\BusinessRuleException;

trait BusinessRuleFlashTrait
{
    /**
     * Exécute une action de service et transforme une BusinessRuleException en flash d'erreur.
     * $successMessage peut être un callable recevant la valeur retournée par $action.
     *
     * @return bool true si l'action a réussi
     */
    private function handleBusinessRule(callable $action, string|callable $successMessage): bool
    {
        try {
            $result = $action();
            $this->addFlash('success', is_callable($successMessage) ? $successMessage($result) : $successMessage);
            return true;
        } catch (BusinessRuleException $e) {
            $this->addFlash('error', $e->getMessage());
            return false;
        }
    }
}
