<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Application\Services;

use Hamzi\Synapse\Domain\DTO\SerialFrame;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

final class MessageRouter
{
    public function __construct(private readonly Dispatcher $events) {}

    public function route(SerialFrame $frame): void
    {
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = (array) config('synapse.mappings', []);

        foreach ($mappings as $mapping) {
            if (! $this->matches($mapping, $frame)) {
                continue;
            }

            $this->dispatchMappedEvent($mapping, $frame);
            $this->persistMappedModel($mapping, $frame);
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function matches(array $mapping, SerialFrame $frame): bool
    {
        $driver = $mapping['driver'] ?? null;
        if (is_string($driver) && $driver !== $frame->driver) {
            return false;
        }

        $payloadField = $mapping['payload_field'] ?? null;
        $matchValue = $mapping['equals'] ?? null;

        if (! is_string($payloadField) || $matchValue === null) {
            return true;
        }

        return ($frame->payload[$payloadField] ?? null) === $matchValue;
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function dispatchMappedEvent(array $mapping, SerialFrame $frame): void
    {
        $eventClass = $mapping['event'] ?? null;
        if (! is_string($eventClass) || ! class_exists($eventClass)) {
            return;
        }

        $eventPayloadField = $mapping['event_payload_field'] ?? 'barcode';

        $value = is_string($eventPayloadField)
            ? ($frame->payload[$eventPayloadField] ?? null)
            : null;

        if (! is_string($value)) {
            $value = json_encode($frame->payload, JSON_THROW_ON_ERROR);
        }

        $this->events->dispatch(new $eventClass($value, $frame->context));
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function persistMappedModel(array $mapping, SerialFrame $frame): void
    {
        $modelClass = $mapping['model'] ?? null;
        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return;
        }

        /** @var Model $model */
        $model = new $modelClass;

        /** @var array<string, string> $fieldMap */
        $fieldMap = (array) ($mapping['field_map'] ?? []);

        $attributes = [];
        foreach ($fieldMap as $modelField => $payloadField) {
            $attributes[$modelField] = $frame->payload[$payloadField] ?? null;
        }

        if ($attributes !== []) {
            $model->fill($attributes);
            $model->save();
        }
    }
}
