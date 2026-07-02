<?php

namespace TCG\Voyager\Database\Schema;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

abstract class SchemaManager
{
    public static function __callStatic($method, $args)
    {
        return static::manager()->$method(...$args);
    }

    public static function manager()
    {
        return DB::connection();
    }

    public static function getDatabaseConnection()
    {
        return DB::connection();
    }

    public static function tableExists($table)
    {
        if (!is_array($table)) {
            $table = [$table];
        }

        return Schema::hasTable($table[0]);
    }

    public static function listTables()
    {
        $tables = [];
        $tableNames = Schema::getConnection()->getSchemaBuilder()->getTables();

        foreach ($tableNames as $tableName) {
            $tables[$tableName] = static::listTableDetails($tableName);
        }

        return $tables;
    }

    public static function listTableDetails($tableName)
    {
        $columns = Schema::getColumnListing($tableName);
        $columnDetails = collect($columns)->mapWithKeys(function ($column) use ($tableName) {
            $details = static::getColumnDetails($tableName, $column);

            // Translate the flat introspection shape into the array shape
            // consumed by Column::make (used by the Table value object).
            return [$column => [
                'name'          => $column,
                'type'          => ['name' => $details['type']],
                'notnull'       => ($details['nullable'] === 'NO'),
                'default'       => $details['default'],
                'autoincrement' => (bool) $details['auto_increment'],
                'length'        => $details['length'] ?? null,
            ]];
        });

        $indexes = static::getTableIndexes($tableName);
        $foreignKeys = static::getTableForeignKeys($tableName);

        return new Table($tableName, $columnDetails->toArray(), $indexes, [], $foreignKeys, []);
    }

    public static function describeTable($tableName)
    {
        $columns = Schema::getColumnListing($tableName);

        return collect($columns)->map(function ($column) use ($tableName) {
            $columnDetails = static::getColumnDetails($tableName, $column);
            $indexes = static::getColumnIndexes($tableName, $column);

            if (!empty($indexes) && isset($indexes[1])) {
                $indexes = [$indexes[1]];
            }

            return [
                'field' => $column,
                'type' => $columnDetails['type'],
                // Mirrors MySQL's DESCRIBE output ("YES"/"NO"), which callers
                // (BREAD edit-add view, the database-manager "Show Table Info"
                // modal, Column::make()) compare against as a string, not a
                // boolean. $columnDetails['nullable'] is actually "not
                // nullable" (see getColumnDetails() below).
                'null' => $columnDetails['nullable'] ? 'NO' : 'YES',
                'key' => !empty($indexes) ? substr($indexes[0]['type'], 0, 3) : null,
                'default' => $columnDetails['default'],
                'extra' => $columnDetails['auto_increment'] ? 'auto_increment' : '',
                'indexes' => $indexes,
            ];
        });
    }

    public static function listTableColumnNames($tableName)
    {
        return Schema::getColumnListing($tableName);
    }

    /**
     * Create a table from a Voyager Table value object (or an array/JSON
     * describing one). Uses Laravel's native Blueprint instead of Doctrine.
     *
     * @param Table|array|string $table
     *
     * @return void
     */
    public static function createTable($table)
    {
        if (!$table instanceof Table) {
            $table = Table::make($table);
        }

        if (static::tableExists($table->getName())) {
            throw new \RuntimeException("table {$table->getName()} already exists");
        }

        Schema::create($table->getName(), function (Blueprint $blueprint) use ($table) {
            $autoIncrementColumns = [];

            foreach ($table->getColumns() as $column) {
                SchemaBuilder::applyColumn($blueprint, $column);

                if ($column->getAutoIncrement()) {
                    $autoIncrementColumns[] = $column->getName();
                }
            }

            foreach ($table->getIndexes() as $index) {
                // An auto-increment column is already the primary key in every
                // engine's grammar; adding an explicit primary index on top of
                // it would create a duplicate primary key.
                if ($index->isPrimary()
                    && !array_diff($index->getColumns(), $autoIncrementColumns)
                    && !empty($autoIncrementColumns)) {
                    continue;
                }

                SchemaBuilder::applyIndex($blueprint, $index);
            }
        });
    }

    /**
     * Drop a table if it exists.
     *
     * @param string $table
     *
     * @return void
     */
    public static function dropTable($table)
    {
        Schema::dropIfExists($table);
    }

    /**
     * Rename a table.
     *
     * @param string $from
     * @param string $to
     *
     * @return void
     */
    public static function renameTable($from, $to)
    {
        Schema::rename($from, $to);
    }

    /**
     * Note: despite the key name, 'nullable' here actually means "not
     * nullable" (it's the inverted native flag) - this feeds Table/Column's
     * `notnull` property directly. Callers that need the native YES/NO
     * DESCRIBE-style semantics (e.g. describeTable()) must invert it back.
     */
    protected static function getColumnDetails($table, $column)
    {
        $schema = Schema::getConnection()->getSchemaBuilder();
        $columnType = $schema->getColumnType($table, $column);
        $columnDefinition = $schema->getColumns($table);

        $columnInfo = collect($columnDefinition)->firstWhere('name', $column);

        if (!$columnInfo) {
            throw new \InvalidArgumentException("Column '$column' not found in table '$table'.");
        }

        return [
            'type' => $columnType,
            'nullable' => !($columnInfo['nullable'] ?? false),
            'default' => static::normaliseDefault($columnInfo['default'] ?? null),
            'auto_increment' => ($columnInfo['auto_increment'] ?? false),
            'length' => static::parseLength($columnInfo['type'] ?? ''),
        ];
    }

    /**
     * Extract a length/precision from a full SQL type string, e.g.
     * "varchar(255)" => 255, "decimal(8,2)" => 8. Returns null when absent.
     *
     * @param string $fullType
     *
     * @return int|null
     */
    protected static function parseLength($fullType)
    {
        if (preg_match('/\((\d+)/', (string) $fullType, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Native schema introspection returns column defaults verbatim from the
     * engine, which for string defaults means the value is wrapped in quotes
     * (e.g. SQLite/PostgreSQL: 'value'). Strip a single layer of enclosing
     * quotes so the UI shows the logical default.
     *
     * @param string|null $default
     *
     * @return string|null
     */
    protected static function normaliseDefault($default)
    {
        if (!is_string($default)) {
            return $default;
        }

        if (strlen($default) >= 2) {
            $first = $default[0];
            $last = $default[strlen($default) - 1];

            if (($first === "'" && $last === "'") || ($first === '"' && $last === '"')) {
                return substr($default, 1, -1);
            }
        }

        return $default;
    }

    protected static function getTableIndexes($table)
    {
        return DB::getSchemaBuilder()->getIndexes($table);
    }

    protected static function getColumnIndexes($table, $column)
    {
        $tableIndexes = static::getTableIndexes($table);
        return collect($tableIndexes)->filter(function ($index) use ($column) {
            return in_array($column, $index['columns']);
        })->toArray();
    }

    protected static function getTableForeignKeys($table)
    {
        return DB::getSchemaBuilder()->getForeignKeys($table);
    }

    public static function listTableNames()
    {
        $tables = Schema::getConnection()->getSchemaBuilder()->getTables();

        return collect($tables)->pluck('name')->values()->all();
    }
}
