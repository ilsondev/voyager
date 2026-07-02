<?php

namespace TCG\Voyager\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Bridges Voyager's schema value objects (Column / Index) onto Laravel's
 * native Blueprint, replacing the SQL generation Doctrine DBAL used to do.
 *
 * The mapping is intentionally engine-agnostic: it relies on Blueprint's own
 * grammar for each connection (MySQL / PostgreSQL / SQLite), so a single map
 * works across the engines Voyager supports.
 */
abstract class SchemaBuilder
{
    /**
     * Map a Voyager type name to the Blueprint method that creates it, and any
     * extra positional arguments beyond the column name.
     *
     * @param Column   $column
     * @param Blueprint $blueprint
     *
     * @return Fluent the created column definition
     */
    public static function applyColumn(Blueprint $blueprint, Column $column)
    {
        $name = $column->getName();
        $type = strtolower($column->getType()->getName());
        $length = $column->getLength();
        $options = $column->getOptions();

        $definition = static::createColumnDefinition($blueprint, $type, $name, $length, $options);

        if ($column->getAutoIncrement()) {
            $definition->autoIncrement();
        }

        if ($column->getUnsigned()) {
            $definition->unsigned();
        }

        if (!$column->getNotnull()) {
            $definition->nullable();
        }

        $default = $column->getDefault();
        if ($default !== null && $default !== '') {
            $definition->default($default);
        }

        return $definition;
    }

    /**
     * @return Fluent
     */
    protected static function createColumnDefinition(Blueprint $blueprint, $type, $name, $length, array $options)
    {
        switch ($type) {
            case 'boolean':
                return $blueprint->boolean($name);

            case 'tinyint':
                return $blueprint->tinyInteger($name);
            case 'smallint':
                return $blueprint->smallInteger($name);
            case 'mediumint':
                return $blueprint->mediumInteger($name);
            case 'int':
            case 'integer':
                return $blueprint->integer($name);
            case 'bigint':
                return $blueprint->bigInteger($name);

            case 'decimal':
            case 'numeric':
            case 'money':
                return $blueprint->decimal(
                    $name,
                    $options['precision'] ?? ($length ?: 8),
                    $options['scale'] ?? 2
                );
            case 'float':
            case 'real':
                return $blueprint->float($name);
            case 'double':
            case 'double precision':
                return $blueprint->double($name);

            case 'char':
            case 'character':
                return $blueprint->char($name, $length ?: 255);
            case 'varchar':
            case 'character varying':
            case 'string':
                return $length ? $blueprint->string($name, $length) : $blueprint->string($name);
            case 'guid':
            case 'uuid':
                return $blueprint->uuid($name);

            case 'tinytext':
            case 'text':
                return $blueprint->text($name);
            case 'mediumtext':
                return $blueprint->mediumText($name);
            case 'longtext':
                return $blueprint->longText($name);

            case 'date':
                return $blueprint->date($name);
            case 'datetime':
                return $blueprint->dateTime($name);
            case 'timestamp':
                return $blueprint->timestamp($name);
            case 'timestamptz':
                return $blueprint->timestampTz($name);
            case 'time':
                return $blueprint->time($name);
            case 'timetz':
                return $blueprint->timeTz($name);
            case 'year':
                return $blueprint->year($name);
            case 'interval':
                return $blueprint->string($name);

            case 'json':
                return $blueprint->json($name);
            case 'jsonb':
                return $blueprint->jsonb($name);
            case 'enum':
                return $blueprint->enum($name, $options['allowed'] ?? []);
            case 'set':
                return $blueprint->set($name, $options['allowed'] ?? []);

            case 'binary':
            case 'varbinary':
            case 'tinyblob':
            case 'blob':
            case 'mediumblob':
            case 'longblob':
            case 'bytea':
            case 'bit':
                return $blueprint->binary($name);

            case 'inet':
                return $blueprint->ipAddress($name);
            case 'macaddr':
                return $blueprint->macAddress($name);

            case 'geometry':
            case 'point':
            case 'linestring':
            case 'polygon':
            case 'multipoint':
            case 'multilinestring':
            case 'multipolygon':
            case 'geometrycollection':
                return $blueprint->geometry($name);

            default:
                // Unknown / engine-specific type: fall back to a string column
                // so table creation still succeeds rather than fataling.
                return $length ? $blueprint->string($name, $length) : $blueprint->string($name);
        }
    }

    /**
     * Apply an index definition to a blueprint (during table creation).
     *
     * @return void
     */
    public static function applyIndex(Blueprint $blueprint, Index $index)
    {
        $columns = $index->getColumns();

        if ($index->isPrimary()) {
            $blueprint->primary($columns, $index->getName());
        } elseif ($index->isUnique()) {
            $blueprint->unique($columns, $index->getName());
        } else {
            $blueprint->index($columns, $index->getName());
        }
    }
}
