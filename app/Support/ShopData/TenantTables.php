<?php

namespace App\Support\ShopData;

use Illuminate\Support\Facades\DB;

/**
 * Finds every database table that holds one shop's data, by following the
 * foreign keys - so the data export and the shop erasure cover a table added
 * next month without anyone remembering to list it.
 *
 * "Direct" tables point at `shops`; everything else is reached through a
 * foreign key from a table already in the set (quote line items via quotes,
 * prices via business partners, ...). Each table gets a SQL condition that
 * selects just that shop's rows. The shop id is an int, and table / column
 * names come from the database's own catalog, never from user input.
 *
 * Not included on purpose: `users` and `shop_user` (people outlive a shop,
 * and membership is handled explicitly), and `consent_records` (the append-only
 * proof of what each person agreed to, which is kept).
 */
class TenantTables
{
    private const NEVER = ['users', 'shop_user', 'consent_records'];

    /**
     * @return array<string, array{where: string, depth: int}> table => condition, in the order found
     */
    public static function forShop(int $shopId): array
    {
        $fks = collect(DB::select(<<<'SQL'
            SELECT c.conrelid::regclass::text AS child, a.attname AS child_col,
                   c.confrelid::regclass::text AS parent, pa.attname AS parent_col
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = c.conkey[1]
            JOIN pg_attribute pa ON pa.attrelid = c.confrelid AND pa.attnum = c.confkey[1]
            WHERE c.contype = 'f' AND array_length(c.conkey, 1) = 1
              AND c.connamespace = 'public'::regnamespace
            SQL));

        $tables = [];

        // Direct: a foreign key to shops, or a plain shop_id column (Spatie's
        // team tables carry shop_id without a key).
        foreach ($fks->where('parent', 'shops') as $fk) {
            $tables[$fk->child] ??= ['where' => self::q($fk->child).'.'.self::q($fk->child_col)." = {$shopId}", 'depth' => 0];
        }

        foreach (DB::select("SELECT table_name FROM information_schema.columns WHERE table_schema = 'public' AND column_name = 'shop_id'") as $row) {
            $tables[$row->table_name] ??= ['where' => self::q($row->table_name).'.'.self::q('shop_id')." = {$shopId}", 'depth' => 0];
        }

        $tables = array_diff_key($tables, array_flip([...self::NEVER, 'shops']));

        // Then breadth-first through foreign keys pointing at what we have.
        $frontier = array_keys($tables);

        while ($frontier) {
            $next = [];

            foreach ($frontier as $parent) {
                foreach ($fks->where('parent', $parent) as $fk) {
                    if (isset($tables[$fk->child]) || in_array($fk->child, [...self::NEVER, 'shops'], true)) {
                        continue;
                    }

                    $tables[$fk->child] = [
                        'where' => self::q($fk->child).'.'.self::q($fk->child_col).' IN (SELECT '.self::q($fk->parent_col).' FROM '.self::q($parent).' WHERE '.$tables[$parent]['where'].')',
                        'depth' => $tables[$parent]['depth'] + 1,
                    ];
                    $next[] = $fk->child;
                }
            }

            $frontier = $next;
        }

        ksort($tables);

        return $tables;
    }

    /**
     * A SQL condition selecting ids of one table's rows for the shop, for
     * matching polymorphic rows (media, activity log) that have no foreign key.
     */
    public static function idsSubquery(string $table, string $where): string
    {
        return 'SELECT '.self::q('id').' FROM '.self::q($table).' WHERE '.$where;
    }

    private static function q(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
