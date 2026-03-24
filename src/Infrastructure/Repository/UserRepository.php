<?php

namespace FarmaVida\Infrastructure\Repository;

use FarmaVida\Core\Database\Database;
use mysqli;

final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $user;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->connection()->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function cpfExists(string $cpf): bool
    {
        $stmt = $this->connection()->prepare("SELECT id FROM usuarios WHERE cpf = ? LIMIT 1");
        $stmt->bind_param('s', $cpf);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function createClient(array $payload): int
    {
        $stmt = $this->connection()->prepare(
            "INSERT INTO usuarios (nome, email, senha, telefone, endereco, cpf)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssss',
            $payload['nome'],
            $payload['email'],
            $payload['senha'],
            $payload['telefone'],
            $payload['endereco'],
            $payload['cpf']
        );
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function connection(): mysqli
    {
        return $this->database->connection();
    }
}
