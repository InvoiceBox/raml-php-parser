<?php

namespace Raml;

use Raml\Types\ObjectType;

/**
 * Singleton class used to register all types in one place
 */
class TypeCollection implements \Iterator
{
    /**
     * Hold the class instance.
     *
     * @var self
     */
    private static $instance;

    /**
     * Collection
     *
     * @var TypeInterface[]
     */
    private $collection = [];

    /**
     * Current position
     *
     * @var string
     */
    private $position = 0;

    /**
     * Types which need to inherit properties from their parent
     *
     * @var ObjectType[]
     */
    private $typesWithInheritance = [];

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * {@inheritDoc}
     */
    public function current(): mixed
    {
        return $this->collection[$this->position];
    }

    /**
     * {@inheritDoc}
     */
    public function key(): mixed
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * {@inheritDoc}
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * {@inheritDoc}
     */
    public function valid(): bool
    {
        return isset($this->collection[$this->position]);
    }

    /**
     * Adds a Type to the collection
     *
     * @param TypeInterface $type Type to add.
     */
    public function add(TypeInterface $type): void
    {
        $this->collection[] = $type;
    }

    /**
     * Remove given Type from the collection
     *
     * @param TypeInterface $typeToRemove Type to remove.
     * @throws \RuntimeException When no type is found.
     */
    public function remove(TypeInterface $typeToRemove): void
    {
        foreach ($this->collection as $key => $type) {
            if ($type === $typeToRemove) {
                unset($this->collection[$key]);

                return;
            }
        }

        throw new \RuntimeException(\sprintf('Cannot remove given type %s', \var_export($typeToRemove, true)));
    }

    /**
     * Retrieves a type by name
     *
     * @param string $name Name of the Type to retrieve.
     * @return TypeInterface Returns Type matching given name if found.
     *
     * @throws \RuntimeException When no type is found.
     */
    public function getTypeByName($name)
    {
        foreach ($this->collection as $type) {
            /** @var $type TypeInterface */
            if ($type->getName() === $name) {
                return $type;
            }
        }

        throw new \RuntimeException(\sprintf('No type found for name %s, list: %s', \var_export($name, true), \var_export($this->collection, true)));
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasTypeByName($name)
    {
        try {
            return $this->getTypeByName($name) instanceof TypeInterface;
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Applies inheritance on all types that have a parent
     */
    public function applyInheritance(): void
    {
        foreach ($this->typesWithInheritance as $type) {
            $type->inheritFromParent();
        }
        // now clear list to prevent applying multiple times on the same objects
        $this->typesWithInheritance = [];
    }

    /**
     * Adds a Type to the list of typesWithInheritance
     *
     * @param ObjectType $type Type to add.
     * @return self
     */
    public function addTypeWithInheritance(ObjectType $type)
    {
        $this->typesWithInheritance[] = $type;

        return $this;
    }

    /**
     * Returns types in a plain multidimensional array
     *
     * @return array Returns plain array.
     */
    public function toArray()
    {
        $types = [];
        foreach ($this->collection as $type) {
            $types[$type->getName()] = $type->toArray();
        }

        return $types;
    }

    /**
     * Clears the TypeCollection of any registered types
     */
    public function clear(): void
    {
        $this->collection = [];
        $this->position = 0;
        $this->typesWithInheritance = [];
    }
}
