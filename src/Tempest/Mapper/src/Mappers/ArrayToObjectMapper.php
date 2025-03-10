<?php

declare(strict_types=1);

namespace Tempest\Mapper\Mappers;

use Tempest\Mapper\Casters\ArrayCaster;
use Tempest\Mapper\Casters\CasterFactory;
use Tempest\Mapper\Exceptions\MissingValuesException;
use Tempest\Mapper\MapFrom;
use Tempest\Mapper\Mapper;
use Tempest\Mapper\Strict;
use Tempest\Mapper\UnknownValue;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\PropertyReflector;
use Tempest\Validation\Validator;
use Throwable;
use function Tempest\Support\arr;

final readonly class ArrayToObjectMapper implements Mapper
{
    public function __construct(
        private CasterFactory $casterFactory,
    ) {
    }

    public function canMap(mixed $from, mixed $to): bool
    {
        if (! is_array($from)) {
            return false;
        }

        try {
            $class = new ClassReflector($to);

            return $class->isInstantiable();
        } catch (Throwable) {
            return false;
        }
    }

    public function map(mixed $from, mixed $to): object
    {
        $class = new ClassReflector($to);

        $object = $this->resolveObject($to);

        $missingValues = [];
        /** @var PropertyReflector[] $unsetProperties */
        $unsetProperties = [];

        $from = arr($from)->unwrap()->toArray();

        $isStrictClass = $class->hasAttribute(Strict::class);

        foreach ($class->getPublicProperties() as $property) {
            if ($property->isVirtual()) {
                continue;
            }

            $propertyName = $this->resolvePropertyName($property, $from);

            if (! array_key_exists($propertyName, $from)) {
                $isStrictProperty = $isStrictClass || $property->hasAttribute(Strict::class);

                if ($property->hasDefaultValue()) {
                    continue;
                }

                if ($isStrictProperty) {
                    $missingValues[] = $propertyName;
                } else {
                    $unsetProperties[] = $property;
                }

                continue;
            }

            $value = $this->resolveValueFromType(
                data: $from[$propertyName],
                property: $property,
                parent: $object,
            );

            if ($value instanceof UnknownValue) {
                $value = $this->resolveValueFromArray(
                    data: $from[$propertyName],
                    property: $property,
                    parent: $object,
                );
            }

            if ($value instanceof UnknownValue) {
                $caster = $this->casterFactory->forProperty($property);

                $value = $caster?->cast($from[$propertyName]) ?? $from[$propertyName];
            }

            $property->setValue($object, $value);
        }

        if ($missingValues !== []) {
            throw new MissingValuesException($to, $missingValues);
        }

        // Non-strict properties that weren't passed are unset,
        // which means that they can now be accessed via `__get`
        foreach ($unsetProperties as $property) {
            if ($property->isVirtual()) {
                continue;
            }

            $property->unset($object);
        }

        $this->validate($object);

        return $object;
    }

    /**
     * @param array<mixed> $from
     */
    private function resolvePropertyName(PropertyReflector $property, array $from): string
    {
        $mapFrom = $property->getAttribute(MapFrom::class);

        if ($mapFrom !== null) {
            return arr($from)->keys()->intersect($mapFrom->names)->first() ?? $property->getName();
        }

        return $property->getName();
    }

    private function resolveObject(mixed $objectOrClass): object
    {
        if (is_object($objectOrClass)) {
            return $objectOrClass;
        }

        return new ClassReflector($objectOrClass)->newInstanceWithoutConstructor();
    }

    private function resolveValueFromType(
        mixed $data,
        PropertyReflector $property,
        object $parent,
    ): mixed {
        $type = $property->getType();

        if ($type->isBuiltIn()) {
            return new UnknownValue();
        }

        $caster = $this->casterFactory->forProperty($property);

        if (! is_array($data)) {
            return $caster?->cast($data) ?? $data;
        }

        $data = $this->withParentRelations(
            $type->asClass(),
            $parent,
            $data,
        );

        return $this->map(
            from: $caster?->cast($data) ?? $data,
            to: $type->getName(),
        );
    }

    private function resolveValueFromArray(
        mixed $data,
        PropertyReflector $property,
        object $parent,
    ): UnknownValue|array {
        $type = $property->getIterableType();

        if ($type === null) {
            return new UnknownValue();
        }

        $values = [];

        $caster = $this->casterFactory->forProperty($property);

        // We'll manually cast array values instead of using the array caster
        if ($caster instanceof ArrayCaster) {
            $caster = null;
        }

        foreach ($data as $key => $item) {
            if (! is_array($item)) {
                $values[$key] = $caster?->cast($item) ?? $item;

                continue;
            }

            $item = $this->withParentRelations(
                $type->asClass(),
                $parent,
                $item,
            );

            $values[] = $this->map(
                from: $caster?->cast($item) ?? $item,
                to: $type->getName(),
            );
        }

        return $values;
    }

    private function validate(mixed $object): void
    {
        $validator = new Validator();

        $validator->validate($object);
    }

    private function withParentRelations(
        ClassReflector $child,
        object $parent,
        array $data,
    ): array {
        foreach ($child->getPublicProperties() as $property) {
            if ($property->getType()->getName() === $parent::class) {
                $data[$property->getName()] = $parent;
            }

            if ($property->getIterableType()?->getName() === $parent::class) {
                $data[$property->getName()] = [$parent];
            }
        }

        return $data;
    }
}
