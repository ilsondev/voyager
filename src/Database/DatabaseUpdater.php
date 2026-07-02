<?php

namespace TCG\Voyager\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Database\Schema\Column;
use TCG\Voyager\Database\Schema\Index;
use TCG\Voyager\Database\Schema\SchemaBuilder;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Database\Schema\Table;
use TCG\Voyager\Database\Types\Type;

/**
 * Applies schema changes coming from the database-manager UI.
 *
 * Historically this diffed two Doctrine Table objects and applied a Doctrine
 * TableDiff. Since Laravel dropped Doctrine DBAL, this now computes the diff
 * from the submitted array against the live table introspection and applies it
 * through Laravel's native Blueprint / Schema builder, engine-agnostically.
 */
class DatabaseUpdater
{
    /** @var array */
    protected $tableArr;

    /** @var Table */
    protected $table;

    /** @var Table */
    protected $originalTable;

    public function __construct(array $tableArr)
    {
        Type::registerCustomPlatformTypes();

        $this->table = Table::make($tableArr);
        $this->tableArr = $tableArr;
        $this->originalTable = SchemaManager::listTableDetails($tableArr['oldName']);
    }

    /**
     * Update the table.
     *
     * @param array|string $table
     *
     * @return void
     */
    public static function update($table)
    {
        if (!is_array($table)) {
            $table = json_decode($table, true);
        }

        if (!SchemaManager::tableExists($table['oldName'])) {
            throw new \RuntimeException("table {$table['oldName']} does not exist");
        }

        $updater = new self($table);

        $updater->updateTable();
    }

    /**
     * Apply all detected changes to the table.
     *
     * @return void
     */
    public function updateTable()
    {
        $originalName = $this->tableArr['oldName'];

        // 1. Rename columns first, so subsequent type/option changes address the
        //    new column names.
        $renamedColumns = $this->getRenamedColumns();
        if (!empty($renamedColumns)) {
            Schema::table($originalName, function (Blueprint $blueprint) use ($renamedColumns) {
                foreach ($renamedColumns as $oldName => $newName) {
                    $blueprint->renameColumn($oldName, $newName);
                }
            });

            // Refresh the introspected table after renaming.
            $this->originalTable = SchemaManager::listTableDetails($originalName);
        }

        // 2. Add / modify / drop columns and indexes.
        $addedColumns = $this->getAddedColumns();
        $droppedColumns = $this->getDroppedColumns();
        $addedIndexes = $this->getAddedIndexes();
        $droppedIndexes = $this->getDroppedIndexes();

        if ($droppedIndexes || $droppedColumns) {
            Schema::table($originalName, function (Blueprint $blueprint) use ($droppedIndexes, $droppedColumns) {
                foreach ($droppedIndexes as $index) {
                    $this->dropIndex($blueprint, $index);
                }
                if ($droppedColumns) {
                    $blueprint->dropColumn($droppedColumns);
                }
            });
        }

        if ($addedColumns) {
            Schema::table($originalName, function (Blueprint $blueprint) use ($addedColumns) {
                foreach ($addedColumns as $column) {
                    SchemaBuilder::applyColumn($blueprint, $column);
                }
            });
        }

        if ($addedIndexes) {
            Schema::table($originalName, function (Blueprint $blueprint) use ($addedIndexes) {
                foreach ($addedIndexes as $index) {
                    SchemaBuilder::applyIndex($blueprint, $index);
                }
            });
        }

        // 3. Change existing columns (type / nullable / default).
        $changedColumns = $this->getChangedColumns();
        if ($changedColumns) {
            Schema::table($originalName, function (Blueprint $blueprint) use ($changedColumns) {
                foreach ($changedColumns as $column) {
                    SchemaBuilder::applyColumn($blueprint, $column)->change();
                }
            });
        }

        // 4. Rename the table last.
        if ($this->table->getName() !== $this->originalTable->getName()) {
            $newName = $this->table->getName();
            if (SchemaManager::tableExists($newName)) {
                throw new \RuntimeException("table {$newName} already exists");
            }
            SchemaManager::renameTable($originalName, $newName);
        }
    }

    protected function dropIndex(Blueprint $blueprint, Index $index)
    {
        if ($index->isPrimary()) {
            $blueprint->dropPrimary($index->getName());
        } elseif ($index->isUnique()) {
            $blueprint->dropUnique($index->getName());
        } else {
            $blueprint->dropIndex($index->getName());
        }
    }

    /* --------------------------------------------------------------------- */
    /* Diff computation                                                      */
    /* --------------------------------------------------------------------- */

    /**
     * @return array<string, string> map of oldName => newName
     */
    protected function getRenamedColumns()
    {
        $renamedColumns = [];

        foreach ($this->tableArr['columns'] as $column) {
            $oldName = $column['oldName'] ?? $column['name'];

            if ($this->originalTable->hasColumn($oldName)) {
                $name = $column['name'];

                if ($name != $oldName) {
                    $renamedColumns[$oldName] = $name;
                }
            }
        }

        return $renamedColumns;
    }

    /**
     * Columns present in the submitted table but not in the original.
     *
     * @return Column[]
     */
    protected function getAddedColumns()
    {
        $added = [];

        foreach ($this->table->getColumns() as $name => $column) {
            if (!$this->originalTable->hasColumn($name)) {
                $added[] = $column;
            }
        }

        return $added;
    }

    /**
     * @return string[] column names to drop
     */
    protected function getDroppedColumns()
    {
        $dropped = [];

        foreach ($this->originalTable->getColumns() as $name => $column) {
            if (!$this->table->hasColumn($name)) {
                $dropped[] = $name;
            }
        }

        return $dropped;
    }

    /**
     * Existing columns whose type / nullability / default changed.
     *
     * @return Column[]
     */
    protected function getChangedColumns()
    {
        $changed = [];

        foreach ($this->table->getColumns() as $name => $column) {
            if (!$this->originalTable->hasColumn($name)) {
                continue;
            }

            $original = $this->originalTable->getColumn($name);

            $typeChanged = strtolower($column->getType()->getName()) !== strtolower($original->getType()->getName());
            $notnullChanged = $column->getNotnull() !== $original->getNotnull();
            $defaultChanged = (string) $column->getDefault() !== (string) $original->getDefault();

            if ($typeChanged || $notnullChanged || $defaultChanged) {
                $changed[] = $column;
            }
        }

        return $changed;
    }

    /**
     * @return Index[]
     */
    protected function getAddedIndexes()
    {
        $added = [];

        foreach ($this->table->getIndexes() as $name => $index) {
            if (!$this->originalTable->hasIndex($name)) {
                $added[] = $index;
            }
        }

        return $added;
    }

    /**
     * @return Index[]
     */
    protected function getDroppedIndexes()
    {
        $dropped = [];

        foreach ($this->originalTable->getIndexes() as $name => $index) {
            if (!$this->table->hasIndex($name)) {
                $dropped[] = $index;
            }
        }

        return $dropped;
    }
}
