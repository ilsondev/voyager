<?php

namespace TCG\Voyager\Database\Schema;

/**
 * Plain value object describing a table index.
 *
 * Replaces the former Doctrine\DBAL\Schema\Index dependency while keeping the
 * public API (getName/getColumns/isPrimary/isUnique/spansColumns/...) used by
 * the database manager, its views and the schema updater.
 */
class Index
{
    public const PRIMARY = 'PRIMARY';
    public const UNIQUE = 'UNIQUE';
    public const INDEX = 'INDEX';

    /** @var string */
    protected $name;

    /** @var array */
    protected $columns;

    /** @var bool */
    protected $isUnique;

    /** @var bool */
    protected $isPrimary;

    /** @var array */
    protected $flags;

    /** @var array */
    protected $options;

    public function __construct(
        string $name,
        array $columns,
        bool $isUnique = false,
        bool $isPrimary = false,
        array $flags = [],
        array $options = []
    ) {
        $this->name = $name;
        $this->columns = array_values($columns);
        $this->isUnique = $isUnique || $isPrimary;
        $this->isPrimary = $isPrimary;
        $this->flags = $flags;
        $this->options = $options;
    }

    public static function make(array $index)
    {
        $columns = $index['columns'];
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        if (isset($index['type']) && $index['type'] !== '') {
            $type = $index['type'];

            $isPrimary = ($type == static::PRIMARY);
            $isUnique = $isPrimary || ($type == static::UNIQUE);
        } else {
            $isPrimary = (bool) ($index['isPrimary'] ?? false);
            $isUnique = (bool) ($index['isUnique'] ?? false);

            if ($isPrimary) {
                $type = static::PRIMARY;
            } elseif ($isUnique) {
                $type = static::UNIQUE;
            } else {
                $type = static::INDEX;
            }
        }

        $name = trim($index['name'] ?? '');
        if (empty($name)) {
            $table = $index['table'] ?? null;
            $name = static::createName($columns, $type, $table);
        } else {
            $name = Identifier::validate($name, 'Index');
        }

        $flags = $index['flags'] ?? [];
        $options = $index['options'] ?? [];

        return new self($name, $columns, $isUnique, $isPrimary, $flags, $options);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function isPrimary()
    {
        return $this->isPrimary;
    }

    public function isUnique()
    {
        return $this->isUnique;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Does this index span exactly the given set of columns?
     *
     * @param array $columns
     *
     * @return bool
     */
    public function spansColumns(array $columns)
    {
        $sameColumns = count($this->columns) === count($columns);

        foreach ($columns as $i => $column) {
            if (!isset($this->columns[$i]) || $this->columns[$i] !== $column) {
                $sameColumns = false;
            }
        }

        return $sameColumns;
    }

    /**
     * @return array
     */
    public static function toArray(self $index)
    {
        $name = $index->getName();
        $columns = $index->getColumns();

        return [
            'name'        => $name,
            'oldName'     => $name,
            'columns'     => $columns,
            'type'        => static::getType($index),
            'isPrimary'   => $index->isPrimary(),
            'isUnique'    => $index->isUnique(),
            'isComposite' => count($columns) > 1,
            'flags'       => $index->getFlags(),
            'options'     => $index->getOptions(),
        ];
    }

    public static function getType(self $index)
    {
        if ($index->isPrimary()) {
            return static::PRIMARY;
        } elseif ($index->isUnique()) {
            return static::UNIQUE;
        }

        return static::INDEX;
    }

    /**
     * Create a default index name.
     *
     * @param array       $columns
     * @param string      $type
     * @param string|null $table
     *
     * @return string
     */
    public static function createName(array $columns, $type, $table = null)
    {
        $table = isset($table) ? trim($table).'_' : '';
        $type = trim($type);
        $name = strtolower($table.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $name);
    }

    public static function availableTypes()
    {
        return [
            static::PRIMARY,
            static::UNIQUE,
            static::INDEX,
        ];
    }
}
