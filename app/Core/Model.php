<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct() { $this->db = Database::connection(); }
    public function all(string $orderBy = 'id DESC'): array { return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$orderBy}")->fetchAll(); }
    public function find(int|string $id): ?array { $s=$this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey}=:id LIMIT 1"); $s->execute(['id'=>$id]); return $s->fetch() ?: null; }
    public function delete(int|string $id): bool { $s=$this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey}=:id"); return $s->execute(['id'=>$id]); }
    public function insert(array $data): int { $c=array_keys($data); $f=implode(',',$c); $p=implode(',', array_map(fn($x)=>':'.$x,$c)); $s=$this->db->prepare("INSERT INTO {$this->table} ({$f}) VALUES ({$p})"); $s->execute($data); return (int)$this->db->lastInsertId(); }
    public function update(int|string $id, array $data): bool { $sets=implode(',', array_map(fn($x)=>"{$x}=:{$x}", array_keys($data))); $data['__id']=$id; $s=$this->db->prepare("UPDATE {$this->table} SET {$sets} WHERE {$this->primaryKey}=:__id"); return $s->execute($data); }
}
