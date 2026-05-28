<?php declare(strict_types=1);
namespace App\Models;
use App\Core\Model;
class Settings extends Model
{
    protected string $table = "settings";
    public function getAllKeyed(): array
    {
        $rows = $this->all("key_name ASC");
        $out = [];
        foreach ($rows as $r) {
            $out[$r["key_name"]] = $r["value"];
        }
        return $out;
    }
    public function set(string $key, string $value): void
    {
        $s = $this->db->prepare(
            "SELECT id FROM settings WHERE key_name=:k LIMIT 1",
        );
        $s->execute(["k" => $key]);
        $e = $s->fetch();
        if ($e) {
            $this->update((int) $e["id"], ["value" => $value]);
        } else {
            $this->insert(["key_name" => $key, "value" => $value]);
        }
    }
}
