<?php

namespace TCG\Voyager\Database\Schema;

use TCG\Voyager\Database\Types\Type;

/**
 * Plain value object describing a database column.
 *
 * Replaces the former Doctrine\DBAL\Schema\Column dependency. It keeps the
 * public getter API (getName/getType/getNotnull/getDefault/getAutoIncrement)
 * that the database-manager controller, views and tests rely on, but is now
 * backed by the native Laravel schema introspection arrays.
 */
class Column
{
    /** @var string */
    protected $name;

    /** @var Type */
    protected $type;

    /** @var array */
    protected $options;

    public function __construct(string $name, Type $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * Build a Column value object from an associative array (as produced by
     * toArray(), the frontend, or native schema introspection).
     *
     * @param array       $column
     * @param string|null $tableName
     *
     * @return static
     */
    public static function make(array $column, ?string $tableName = null)
    {
        $name = Identifier::validate($column['name'], 'Column');

        $type = $column['type'];
        if ($type instanceof Type) {
            $typeInstance = $type;
        } else {
            $typeName = is_array($type) ? trim($type['name']) : trim((string) $type);
            $typeInstance = Type::hasType($typeName)
                ? Type::getType($typeName)
                : new class($typeName) extends Type {
                    private $dynamicName;

                    public function __construct($name)
                    {
                        $this->dynamicName = $name;
                    }

                    public function getName()
                    {
                        return $this->dynamicName;
                    }
                };
        }
        $typeInstance->tableName = $tableName;

        $options = array_diff_key(
            $column,
            array_flip(['name', 'composite', 'oldName', 'null', 'extra', 'type', 'charset', 'collation'])
        );

        // Normalise the "notnull" flag: it may arrive as notnull or as null=YES/NO.
        if (!array_key_exists('notnull', $options) && array_key_exists('null', $column)) {
            $options['notnull'] = ($column['null'] === 'NO');
        }

        return new self($name, $typeInstance, $options);
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    public function getNotnull()
    {
        return (bool) ($this->options['notnull'] ?? false);
    }

    public function getDefault()
    {
        return $this->options['default'] ?? null;
    }

    public function getAutoIncrement()
    {
        return (bool) ($this->options['autoincrement'] ?? false);
    }

    public function getUnsigned()
    {
        return (bool) ($this->options['unsigned'] ?? false);
    }

    public function getLength()
    {
        return $this->options['length'] ?? null;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getOption($name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Export the column to its array representation, matching the shape the
     * Vue-based database manager frontend consumes.
     *
     * @param self $column
     *
     * @return array
     */
    public static function toArray(self $column)
    {
        $columnArr = array_merge([
            'name'          => $column->getName(),
            'notnull'       => $column->getNotnull(),
            'length'        => $column->getLength(),
            'precision'     => $column->getOption('precision', 10),
            'scale'         => $column->getOption('scale', 0),
            'fixed'         => $column->getOption('fixed', false),
            'unsigned'      => $column->getUnsigned(),
            'autoincrement' => $column->getAutoIncrement(),
            'default'       => $column->getDefault(),
        ], $column->getOptions());

        $columnArr['type'] = Type::toArray($column->getType());
        $columnArr['name'] = $column->getName();
        $columnArr['oldName'] = $column->getName();
        $columnArr['null'] = $column->getNotnull() ? 'NO' : 'YES';
        $columnArr['extra'] = static::getExtra($column);
        $columnArr['composite'] = false;

        return $columnArr;
    }

    /**
     * @return string
     */
    protected static function getExtra(self $column)
    {
        return $column->getAutoIncrement() ? 'auto_increment' : '';
    }
}
