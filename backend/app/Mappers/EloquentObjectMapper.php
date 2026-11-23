<?php

namespace App\Mappers;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/** Explicit loaded-attribute projection avoids Eloquent __isset(null) and relation lazy loading. */
final class EloquentObjectMapper
{
    public function __construct(private readonly ObjectMapperInterface $mapper) {}

    /**
     * @template T of object
     * @param class-string<T> $targetClass
     * @param list<string> $fields Developer-owned allowlist, never request-supplied field names.
     * @param array<string, mixed> $preparedValues Values already projected by the domain mapper.
     * @return T
     */
    public function map(Model $source, string $targetClass, array $fields, array $preparedValues = []): object
    {
        $attributes = $source->getAttributes();
        $projection = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $preparedValues)) {
                throw new LogicException('Prepared mapping values cannot override model attributes in the allowlist.');
            }
            if (!array_key_exists($field, $attributes)) {
                throw new LogicException('Only loaded model attributes may be mapped.');
            }
            $projection[$field] = $source->getAttribute($field);
        }

        return $this->mapper->map((object) ($projection + $preparedValues), $targetClass);
    }
}
