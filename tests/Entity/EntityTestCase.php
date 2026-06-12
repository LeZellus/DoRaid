<?php

namespace App\Tests\Entity;

use PHPUnit\Framework\TestCase;

abstract class EntityTestCase extends TestCase
{
    /** Injecte un ID sur une entité dont la propriété $id est privée et sans setter public. */
    protected function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity::class, 'id'))->setValue($entity, $id);
    }

    /** Injecte n'importe quelle propriété privée sans setter public (ex: membership sur Character). */
    protected function setProperty(object $entity, string $property, mixed $value): void
    {
        (new \ReflectionProperty($entity::class, $property))->setValue($entity, $value);
    }
}
