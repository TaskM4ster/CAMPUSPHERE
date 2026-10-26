<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Campus_model - base class for all CAMPUSPHERE models.
 *
 * Wraps the shared PDO handle in small, always-parameterised helpers. Every value
 * reaches MySQL through a bound parameter; only integers that were cast with (int)
 * (LIMIT / OFFSET) are interpolated.
 *
 * Mutating methods return a result array:
 *     ['ok' => true,  'message' => '...', 'id' => 123]
 *     ['ok' => false, 'error'   => '...']
 */
class Campus_model extends Model
{
    protected function rows($sql, array $p = [])
    {
        return $this->db->raw($sql, $p)->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function row($sql, array $p = [])
    {
        $r = $this->db->raw($sql, $p)->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    protected function val($sql, array $p = [])
    {
        $r = $this->db->raw($sql, $p)->fetch(PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    /** Run INSERT/UPDATE/DELETE, returns affected rows. */
    protected function run($sql, array $p = [])
    {
        return $this->db->raw($sql, $p)->rowCount();
    }

    /** Insert an associative array, returns the new id. */
    protected function insert_row($table, array $data)
    {
        $cols = implode(', ', array_map(function ($c) { return '`' . str_replace('`', '', $c) . '`'; }, array_keys($data)));
        $ph   = implode(', ', array_fill(0, count($data), '?'));
        $this->db->raw("INSERT INTO `{$table}` ({$cols}) VALUES ({$ph})", array_values($data));
        return (int) $this->db->last_id();
    }

    /** Update by primary key. */
    protected function update_row($table, $id, array $data, $pk = 'id')
    {
        $set = implode(', ', array_map(function ($c) { return '`' . str_replace('`', '', $c) . '`' . ' = ?'; }, array_keys($data)));
        return $this->run("UPDATE `{$table}` SET {$set} WHERE `{$pk}` = ?", array_merge(array_values($data), [$id]));
    }

    protected function ok($message = '', array $extra = [])
    {
        return array_merge(['ok' => true, 'message' => $message], $extra);
    }

    protected function fail($error)
    {
        return ['ok' => false, 'error' => $error];
    }

    /** Active school year row (cached per request). */
    public function sy()
    {
        static $sy = null;
        if ($sy === null) {
            $sy = $this->row('SELECT * FROM school_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
            if (!$sy) {
                $sy = $this->row('SELECT * FROM school_years ORDER BY id DESC LIMIT 1');
            }
        }
        return $sy;
    }

    /** Sanitise a page number / page size for LIMIT. */
    protected function page($page, $per, $max = 200)
    {
        $per  = max(1, min($max, (int) $per));
        $page = max(1, (int) $page);
        return [$page, $per, ($page - 1) * $per];
    }
}
