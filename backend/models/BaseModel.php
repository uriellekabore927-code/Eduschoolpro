<?php

require_once __DIR__ . '/../config/database.php';

abstract class BaseModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    protected function buildUpdateSet(array $fields): string
    {
        return implode(', ', array_map(static fn($field) => "{$field} = :{$field}", $fields));
    }
}
