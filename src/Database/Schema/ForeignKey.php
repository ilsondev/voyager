<?php

namespace TCG\Voyager\Database\Schema;

/**
 * Plain value object describing a foreign-key constraint.
 *
 * Replaces the former Doctrine\DBAL\Schema\ForeignKeyConstraint dependency
 * while keeping the getter API consumed by the database manager and its views.
 */
class ForeignKey
{
    /** @var string */
    protected $name;

    /** @var string|null */
    protected $localTable;

    /** @var array */
    protected $localColumns;

    /** @var string */
    protected $foreignTable;

    /** @var array */
    protected $foreignColumns;

    /** @var array */
    protected $options;

    public function __construct(
        string $name,
        array $localColumns,
        string $foreignTable,
        array $foreignColumns,
        ?string $localTable = null,
        array $options = []
    ) {
        $this->name = $name;
        $this->localColumns = array_values($localColumns);
        $this->foreignTable = $foreignTable;
        $this->foreignColumns = array_values($foreignColumns);
        $this->localTable = $localTable;
        $this->options = $options;
    }

    public static function make(array $foreignKey)
    {
        $localTable = $foreignKey['localTable'] ?? null;
        $localColumns = (array) $foreignKey['localColumns'];
        $foreignTable = $foreignKey['foreignTable'];
        $foreignColumns = (array) $foreignKey['foreignColumns'];
        $options = $foreignKey['options'] ?? [];

        $name = isset($foreignKey['name']) ? trim($foreignKey['name']) : '';
        if (empty($name)) {
            $name = Index::createName($localColumns, 'foreign', $localTable);
        } else {
            $name = Identifier::validate($name, 'Foreign Key');
        }

        return new self($name, $localColumns, $foreignTable, $foreignColumns, $localTable, $options);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLocalTableName()
    {
        return $this->localTable;
    }

    public function getLocalColumns()
    {
        return $this->localColumns;
    }

    public function getForeignTableName()
    {
        return $this->foreignTable;
    }

    public function getForeignColumns()
    {
        return $this->foreignColumns;
    }

    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public static function toArray(self $fk)
    {
        return [
            'name'           => $fk->getName(),
            'localTable'     => $fk->getLocalTableName(),
            'localColumns'   => $fk->getLocalColumns(),
            'foreignTable'   => $fk->getForeignTableName(),
            'foreignColumns' => $fk->getForeignColumns(),
            'options'        => $fk->getOptions(),
        ];
    }
}
