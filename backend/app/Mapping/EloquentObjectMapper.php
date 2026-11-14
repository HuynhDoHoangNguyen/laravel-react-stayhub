<?php

namespace App\Mapping;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/** Explicit loaded-attribute projection avoids Eloquent __isset(null) and relation lazy loading. */
final class EloquentObjectMapper
{
    public function __construct(private readonly ObjectMapperInterface $mapper) {}

    /** @param class-string $targetClass
     *  @param list<string> $fields Developer-owned allowlist, never request-supplied field names.
     */
    public function map(Model $source, string $targetClass, array $fields): object
    {
        $attributes = $source->getAttributes();
        $projection = new \stdClass;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $attributes)) {
                throw new LogicException('Only loaded model attributes may be mapped.');
            }
            $projection->{$field} = $source->getAttribute($field);
        }
        return $this->mapper->map($projection, $targetClass);
    }
}
