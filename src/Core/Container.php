<?php

namespace FarmaVida\Core;

use RuntimeException;

final class Container
{
    private array $bindings = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            throw new RuntimeException("Serviço não registrado: {$id}");
        }

        $this->instances[$id] = ($this->bindings[$id])($this);
        return $this->instances[$id];
    }
}
