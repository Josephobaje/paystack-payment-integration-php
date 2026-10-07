<?php

declare(strict_types=1);

namespace App\Orders;

use PDO;

final class OrderRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->migrate();
    }

    public static function sqlite(string $file): self
    {
        if ($file !== ':memory:' && !is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        return new self(new PDO('sqlite:' . $file));
    }

    private function migrate(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reference TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL,
                product TEXT NOT NULL,
                amount_kobo INTEGER NOT NULL,
                currency TEXT NOT NULL DEFAULT \'NGN\',
                status TEXT NOT NULL DEFAULT \'pending\',
                channel TEXT,
                gateway_response TEXT,
                paid_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    public function create(string $reference, string $email, string $product, int $amountKobo, string $currency = 'NGN'): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO orders (reference, email, product, amount_kobo, currency) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$reference, $email, $product, $amountKobo, $currency]);
    }

    /** @return array<string,mixed>|null */
    public function find(string $reference): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE reference = ?');
        $stmt->execute([$reference]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Marks an order paid only once. Returns false if it was already paid (idempotent). */
    public function markPaid(string $reference, string $channel, string $gatewayResponse, string $paidAt): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE orders SET status = 'paid', channel = ?, gateway_response = ?, paid_at = ? WHERE reference = ? AND status != 'paid'"
        );
        $stmt->execute([$channel, $gatewayResponse, $paidAt, $reference]);
        return $stmt->rowCount() === 1;
    }

    public function markFailed(string $reference, string $gatewayResponse): void
    {
        $stmt = $this->pdo->prepare("UPDATE orders SET status = 'failed', gateway_response = ? WHERE reference = ? AND status = 'pending'");
        $stmt->execute([$gatewayResponse, $reference]);
    }

    /** @return list<array<string,mixed>> */
    public function latest(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
