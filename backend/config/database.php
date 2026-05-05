<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'campusflow_db';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '&Admin@2025!';

        $candidates = [];
        $seen = [];

        $pushCandidate = static function (string $candidateHost, string $candidateName) use (&$candidates, &$seen): void {
            $key = $candidateHost . '|' . $candidateName;
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $candidates[] = [$candidateHost, $candidateName];
        };

        $pushCandidate($host, $name);

        if ($host === 'localhost') {
            $pushCandidate('127.0.0.1', $name);
        }

        if ($name === 'eduschedule_db') {
            $pushCandidate($host, 'campusflow_db');
            if ($host === 'localhost') {
                $pushCandidate('127.0.0.1', 'campusflow_db');
            }
        }

        $pushCandidate('127.0.0.1', 'campusflow_db');

        $lastException = null;

        foreach ($candidates as [$candidateHost, $candidateName]) {
            $dsn = "mysql:host={$candidateHost};port={$port};dbname={$candidateName};charset=utf8mb4";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                return self::$instance;
            } catch (PDOException $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException instanceof PDOException) {
            throw $lastException;
        }

        return self::$instance;
    }
}
