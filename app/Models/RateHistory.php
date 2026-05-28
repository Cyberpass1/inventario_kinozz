<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
class RateHistory extends Model
{
    protected string $table = "exchange_rates";

    public function latest(): ?array
    {
        $r = $this->db
            ->query(
                "SELECT * FROM exchange_rates ORDER BY rate_date DESC, id DESC LIMIT 1",
            )
            ->fetch();
        return $r ?: null;
    }

    public function forDate(string $date): ?array
    {
        $date = trim($date);
        if ($date === '') {
            return $this->latest();
        }

        $statement = $this->db->prepare(
            "SELECT * FROM exchange_rates WHERE rate_date <= ? ORDER BY rate_date DESC, id DESC LIMIT 1"
        );
        $statement->execute([$date]);
        $row = $statement->fetch();

        return $row ?: $this->latest();
    }

    public function latestForPair(string $from, string $to): ?array
    {
        $statement = $this->db->prepare(
            "SELECT * FROM exchange_rates
             WHERE currency_from = ?
               AND currency_to = ?
             ORDER BY rate_date DESC, id DESC
             LIMIT 1"
        );
        $statement->execute([strtoupper(trim($from)), strtoupper(trim($to))]);

        return $statement->fetch() ?: null;
    }

    public function forPairOnDate(string $date, string $from, string $to): ?array
    {
        $statement = $this->db->prepare(
            "SELECT * FROM exchange_rates
             WHERE rate_date <= ?
               AND currency_from = ?
               AND currency_to = ?
             ORDER BY rate_date DESC, id DESC
             LIMIT 1"
        );
        $statement->execute([
            trim($date),
            strtoupper(trim($from)),
            strtoupper(trim($to)),
        ]);

        return $statement->fetch() ?: null;
    }

    public function upsertDaily(string $date, string $from, string $to, float $rate): void
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        $date = trim($date);

        $existing = $this->db->prepare(
            "SELECT id
             FROM exchange_rates
             WHERE rate_date = ?
               AND currency_from = ?
               AND currency_to = ?
             ORDER BY id DESC
             LIMIT 1"
        );
        $existing->execute([$date, $from, $to]);
        $row = $existing->fetch();

        if ($row) {
            $this->update((int) $row['id'], ['rate' => $rate]);
            return;
        }

        $this->insert([
            'rate_date' => $date,
            'currency_from' => $from,
            'currency_to' => $to,
            'rate' => $rate,
        ]);
    }
}
