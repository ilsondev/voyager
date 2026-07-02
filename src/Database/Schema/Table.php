<?php

namespace TCG\Voyager\Database\Schema;

use TCG\Voyager\Database\Types\Type;

/**
 * Plain value object describing a database table and its schema.
 *
 * Replaces the former Doctrine\DBAL\Schema\Table dependency. It accepts either
 * the raw arrays returned by Laravel's native schema introspection
 * (Schema::getColumns()/getIndexes()/getForeignKeys()) or the arrays produced
 * by the database manager frontend, and normalises them into Column/Index/
 * ForeignKey value objects, exposing the same getter API the rest of Voyager
 * (controller, Blade/Vue views, tests) already consumes.
 */
class Table
{
    /** @var string */
    protected $name;

    /** @var Column[] keyed by column name */
    protected $columns = [];

    /** @var Index[] keyed by index name */
    protected $indexes = [];

    /** @var ForeignKey[] keyed by foreign-key name */
    protected $foreignKeys = [];

    /** @var array */
    protected $options = [];

    /** @var string|false */
    protected $primaryKeyName = false;

    /**
     * @param string $name
     * @param array  $columns     array of Column instances or column arrays
     * @param array  $indexes     array of Index instances or index arrays
     * @param array  $uniqueKeys  ignored (kept for signature compatibility)
     * @param array  $foreignKeys array of ForeignKey instances or fk arrays
     * @param array  $options
     */
    public function __construct(
        string $name,
        array $columns = [],
        array $indexes = [],
        array $uniqueKeys = [],
        array $foreignKeys = [],
        array $options = []
    ) {
        $this->name = $name;
        $this->options = $options;

        foreach ($columns as $key => $column) {
            $this->addColumnObject($this->normaliseColumn($column, is_string($key) ? $key : null));
        }

        foreach ($indexes as $index) {
            $this->addIndexObject($this->normaliseIndex($index));
        }

        foreach ($foreignKeys as $foreignKey) {
            $this->addForeignKeyObject($this->normaliseForeignKey($foreignKey));
        }
    }

    /**
     * Build a Table from an array or JSON payload (frontend / stored schema).
     *
     * @param array|string $table
     *
     * @return static
     */
    public static function make($table)
    {
        if (!is_array($table)) {
            $table = json_decode($table, true);
        }

        $name = Identifier::validate($table['name'], 'Table');

        $instance = new self($name, [], [], [], [], $table['options'] ?? []);

        foreach ($table['columns'] ?? [] as $columnArr) {
            $column = Column::make($columnArr, $name);
            $instance->addColumnObject($column);
        }

        foreach ($table['indexes'] ?? [] as $indexArr) {
            $index = Index::make($indexArr);
            $instance->addIndexObject($index);
        }

        foreach ($table['foreignKeys'] ?? [] as $foreignKeyArr) {
            $foreignKey = ForeignKey::make($foreignKeyArr);
            $instance->addForeignKeyObject($foreignKey);
        }

        if (!empty($table['primaryKeyName'])) {
            $instance->primaryKeyName = $table['primaryKeyName'];
        }

        return $instance;
    }

    /* --------------------------------------------------------------------- */
    /* Normalisation helpers                                                 */
    /* --------------------------------------------------------------------- */

    protected function normaliseColumn($column, $nameHint = null)
    {
        if ($column instanceof Column) {
            return $column;
        }

        if (is_array($column)) {
            if (!isset($column['name']) && $nameHint !== null) {
                $column['name'] = $nameHint;
            }

            return Column::make($column, $this->name);
        }

        throw new \InvalidArgumentException('Invalid column definition.');
    }

    protected function normaliseIndex($index)
    {
        if ($index instanceof Index) {
            return $index;
        }

        if (is_array($index)) {
            // Native Laravel getIndexes() rows use "primary"/"unique" booleans
            // and "name"/"columns" keys; map them to the Index::make() shape.
            if (!isset($index['isPrimary']) && array_key_exists('primary', $index)) {
                $index['isPrimary'] = (bool) $index['primary'];
            }
            if (!isset($index['isUnique']) && array_key_exists('unique', $index)) {
                $index['isUnique'] = (bool) $index['unique'];
            }
            $index['table'] = $index['table'] ?? $this->name;

            return Index::make($index);
        }

        throw new \InvalidArgumentException('Invalid index definition.');
    }

    protected function normaliseForeignKey($foreignKey)
    {
        if ($foreignKey instanceof ForeignKey) {
            return $foreignKey;
        }

        if (is_array($foreignKey)) {
            // Native getForeignKeys() rows use columns/foreign_table/foreign_columns.
            $mapped = [
                'name'           => $foreignKey['name'] ?? '',
                'localTable'     => $foreignKey['localTable'] ?? $this->name,
                'localColumns'   => $foreignKey['localColumns'] ?? ($foreignKey['columns'] ?? []),
                'foreignTable'   => $foreignKey['foreignTable'] ?? ($foreignKey['foreign_table'] ?? ''),
                'foreignColumns' => $foreignKey['foreignColumns'] ?? ($foreignKey['foreign_columns'] ?? []),
                'options'        => $foreignKey['options'] ?? [],
            ];

            return ForeignKey::make($mapped);
        }

        throw new \InvalidArgumentException('Invalid foreign key definition.');
    }

    /* --------------------------------------------------------------------- */
    /* Mutators (fluent, mirror the old Doctrine API)                        */
    /* --------------------------------------------------------------------- */

    protected function addColumnObject(Column $column)
    {
        $this->columns[$column->getName()] = $column;

        return $column;
    }

    protected function addIndexObject(Index $index)
    {
        $this->indexes[$index->getName()] = $index;

        if ($index->isPrimary() && !$this->primaryKeyName) {
            $this->primaryKeyName = $index->getName();
        }

        return $index;
    }

    protected function addForeignKeyObject(ForeignKey $foreignKey)
    {
        $this->foreignKeys[$foreignKey->getName()] = $foreignKey;

        return $foreignKey;
    }

    /**
     * @param string $name
     * @param string $typeName
     * @param array  $options
     *
     * @return Column
     */
    public function addColumn($name, $typeName, array $options = [])
    {
        $column = Column::make(
            array_merge($options, ['name' => $name, 'type' => ['name' => $typeName]]),
            $this->name
        );

        return $this->addColumnObject($column);
    }

    public function setPrimaryKey(array $columns, $name = 'primary')
    {
        $index = new Index($name, $columns, true, true);
        $this->indexes[$index->getName()] = $index;
        $this->primaryKeyName = $index->getName();

        return $this;
    }

    public function addUniqueIndex(array $columns, $name = null)
    {
        if (empty($name)) {
            $name = Index::createName($columns, Index::UNIQUE, $this->name);
        }

        $index = new Index($name, $columns, true, false);
        $this->indexes[$index->getName()] = $index;

        return $this;
    }

    public function addIndex(array $columns, $name = null)
    {
        if (empty($name)) {
            $name = Index::createName($columns, Index::INDEX, $this->name);
        }

        $index = new Index($name, $columns, false, false);
        $this->indexes[$index->getName()] = $index;

        return $this;
    }

    /* --------------------------------------------------------------------- */
    /* Accessors                                                             */
    /* --------------------------------------------------------------------- */

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Column[]
     */
    public function getColumns()
    {
        return $this->columns;
    }

    public function getColumn($name)
    {
        if (!$this->hasColumn($name)) {
            throw new \InvalidArgumentException("Column '{$name}' does not exist on table '{$this->name}'.");
        }

        return $this->columns[$name];
    }

    public function hasColumn($name)
    {
        return isset($this->columns[$name]);
    }

    /**
     * @return Index[]
     */
    public function getIndexes()
    {
        return $this->indexes;
    }

    public function getIndex($name)
    {
        if (!$this->hasIndex($name)) {
            throw new \InvalidArgumentException("Index '{$name}' does not exist on table '{$this->name}'.");
        }

        return $this->indexes[$name];
    }

    public function hasIndex($name)
    {
        return isset($this->indexes[$name]);
    }

    /**
     * @return Index|null
     */
    public function getPrimaryKey()
    {
        if ($this->primaryKeyName && isset($this->indexes[$this->primaryKeyName])) {
            return $this->indexes[$this->primaryKeyName];
        }

        foreach ($this->indexes as $index) {
            if ($index->isPrimary()) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return ForeignKey[]
     */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Return the indexes that span (exactly) the given column(s).
     *
     * @param array|string $columns
     * @param bool         $sort
     *
     * @return Index[]
     */
    public function getColumnsIndexes($columns, $sort = false)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        $matched = [];

        foreach ($this->indexes as $index) {
            if ($index->spansColumns($columns)) {
                $matched[$index->getName()] = $index;
            }
        }

        if (count($matched) > 1 && $sort) {
            uasort($matched, function ($index1, $index2) {
                $index1_type = Index::getType($index1);
                $index2_type = Index::getType($index2);

                if ($index1_type == $index2_type) {
                    return 0;
                }

                if ($index1_type == Index::PRIMARY) {
                    return -1;
                }

                if ($index2_type == Index::PRIMARY) {
                    return 1;
                }

                if ($index1_type == Index::UNIQUE) {
                    return -1;
                }

                return 1;
            });
        }

        return $matched;
    }

    /* --------------------------------------------------------------------- */
    /* Export                                                                */
    /* --------------------------------------------------------------------- */

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'name'           => $this->name,
            'oldName'        => $this->name,
            'columns'        => $this->exportColumnsToArray(),
            'indexes'        => $this->exportIndexesToArray(),
            'primaryKeyName' => $this->primaryKeyName,
            'foreignKeys'    => $this->exportForeignKeysToArray(),
            'options'        => $this->options,
        ];
    }

    /**
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * @return array
     */
    public function exportColumnsToArray()
    {
        $exportedColumns = [];

        foreach ($this->columns as $column) {
            $exportedColumns[] = Column::toArray($column);
        }

        return $exportedColumns;
    }

    /**
     * @return array
     */
    public function exportIndexesToArray()
    {
        $exportedIndexes = [];

        foreach ($this->indexes as $index) {
            $indexArr = Index::toArray($index);
            $indexArr['table'] = $this->name;
            $exportedIndexes[] = $indexArr;
        }

        return $exportedIndexes;
    }

    /**
     * @return array
     */
    public function exportForeignKeysToArray()
    {
        $exportedForeignKeys = [];

        foreach ($this->foreignKeys as $name => $fk) {
            $exportedForeignKeys[$name] = ForeignKey::toArray($fk);
        }

        return $exportedForeignKeys;
    }

    public function __get($property)
    {
        $getter = 'get'.ucfirst($property);

        if (!method_exists($this, $getter)) {
            throw new \Exception("Property {$property} doesn't exist or is unavailable");
        }

        return $this->$getter();
    }
}
