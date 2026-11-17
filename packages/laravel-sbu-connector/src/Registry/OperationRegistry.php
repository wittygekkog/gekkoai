<?php

declare(strict_types=1);

namespace WittyGekko\SbuConnector\Registry;

use WittyGekko\SbuConnector\Exceptions\UnknownOperationException;

/**
 * Explicit allowlist of operations a host application is willing to run.
 * Registration cannot lower an existing risk level.
 */
final class OperationRegistry
{
    /** @var array<string, OperationDefinition> */
    private array $operations = [];

    public function register(OperationDefinition $definition): void
    {
        $existing = $this->operations[$definition->name] ?? null;
        if ($existing !== null && $this->rank($definition->risk) < $this->rank($existing->risk)) {
            throw new \RuntimeException('Refusing to lower the registered risk of '.$definition->name.'.');
        }

        $this->operations[$definition->name] = $definition;
    }

    public function has(string $name): bool
    {
        return isset($this->operations[$name]);
    }

    public function get(string $name): OperationDefinition
    {
        if (! isset($this->operations[$name])) {
            throw new UnknownOperationException($name);
        }

        return $this->operations[$name];
    }

    /**
     * @return array<string, OperationDefinition>
     */
    public function all(): array
    {
        return $this->operations;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return array_values(array_map(
            static fn (OperationDefinition $definition): array => $definition->toCatalog(),
            $this->operations,
        ));
    }

    private function rank(string $risk): int
    {
        return match ($risk) {
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
            default => 4,
        };
    }
}
