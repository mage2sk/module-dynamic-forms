<?php
declare(strict_types=1);

namespace Panth\DynamicForms\Test\Unit\Support;

/**
 * Builds template blocks without their heavy framework constructor, injecting only
 * the collaborators the method under test actually uses.
 */
trait BlockBuilderTrait
{
    protected function buildBlock(string $class, array $properties, array $data = [])
    {
        $block = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

        foreach ($properties as $name => $value) {
            $this->injectProperty($block, $name, $value);
        }
        if ($data !== []) {
            $block->setData($data);
        }

        return $block;
    }

    private function injectProperty(object $object, string $name, $value): void
    {
        $reflection = new \ReflectionClass($object);
        while ($reflection !== false) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue($object, $value);
                return;
            }
            $reflection = $reflection->getParentClass();
        }

        throw new \LogicException('Unknown property ' . $name);
    }
}
