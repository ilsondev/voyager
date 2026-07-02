<?php

namespace TCG\Voyager\Tests;

use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Database\Schema\SchemaManager;
use TCG\Voyager\Database\Schema\Table;
use TCG\Voyager\Database\Types\Type;
use TCG\Voyager\Traits\AlertsMessages;

/**
 * End-to-end tests for the Database Manager, exercising the native (Laravel
 * Blueprint based) schema layer that replaced Doctrine DBAL in Phase 2.
 *
 * Runs against SQLite (:memory:), the engine used by the test bootstrap; the
 * same code paths drive MySQL/PostgreSQL through Laravel's per-connection
 * grammar.
 */
class DatabaseTest extends TestCase
{
    use AlertsMessages;

    protected $table;

    protected $createResponse;

    public function setUp(): void
    {
        parent::setUp();

        Type::registerCustomPlatformTypes(true);
        Auth::loginUsingId(1);

        // Prepare table definition.
        $newTable = new Table('test_table_new');
        $newTable->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull'       => true,
        ]);
        $newTable->addColumn('details', 'text', [
            'notnull' => true,
        ]);
        $newTable->setPrimaryKey(['id'], 'primary');

        $this->table = $newTable->toArray();

        // Create the table through the HTTP endpoint.
        $this->createResponse = $this->post(route('voyager.database.store'), [
            'table' => json_encode($this->table),
        ]);
    }

    public function test_table_created_successfully(): void
    {
        $this->createResponse->assertSessionHas('alerts');
        $this->createResponse->assertRedirect(route('voyager.database.index'));
        $this->assertTrue(SchemaManager::tableExists($this->table['name']));

        $dbTable = SchemaManager::listTableDetails($this->table['name']);

        $id = $dbTable->getColumn('id');
        $details = $dbTable->getColumn('details');

        // Column types.
        $this->assertEquals('integer', $id->getType()->getName());
        $this->assertEquals('text', $details->getType()->getName());

        // Auto increment / not null.
        $this->assertTrue($id->getAutoIncrement());
        $this->assertTrue($details->getNotnull());

        // Primary key.
        $primary = $dbTable->getPrimaryKey();
        $this->assertNotNull($primary);
        $this->assertTrue($primary->isPrimary());

        // Creating a table that already exists throws.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("table {$this->table['name']} already exists");
        SchemaManager::createTable($this->table);
    }

    /**
     * describeTable() mirrors MySQL's DESCRIBE output: the 'null' field must
     * be the string "YES"/"NO", not a boolean. Consumers compare it as a
     * string (BREAD edit-add view's required-field detection, Column::make(),
     * the database-manager "Show Table Info" modal via JSON).
     */
    public function test_describe_table_reports_null_as_yes_no_string(): void
    {
        $table = new Table('test_table_describe');
        $table->addColumn('id', 'integer', ['autoincrement' => true, 'notnull' => true]);
        $table->addColumn('required_field', 'text', ['notnull' => true]);
        $table->addColumn('optional_field', 'text', ['notnull' => false]);
        $table->setPrimaryKey(['id'], 'primary');

        SchemaManager::createTable($table->toArray());

        $description = SchemaManager::describeTable('test_table_describe')->keyBy('field');

        $this->assertSame('NO', $description['required_field']['null']);
        $this->assertSame('YES', $description['optional_field']['null']);
    }

    public function test_can_update_table(): void
    {
        $this->update_table_that_not_exist();
        $this->can_add_column();
        $this->can_change_column_type();
        $this->can_change_column_options();
        $this->can_add_index();
        $this->can_rename_column();
        $this->can_drop_column();
        $this->can_rename_table();
    }

    public function test_can_drop_table(): void
    {
        $this->assertTrue(SchemaManager::tableExists($this->table['name']));

        $response = $this->delete(route('voyager.database.destroy', $this->table['name']));

        $response->assertSessionHas('alerts');
        $response->assertRedirect(route('voyager.database.index'));
        $this->assertFalse(SchemaManager::tableExists($this->table['name']));
    }

    protected function update_table_that_not_exist(): void
    {
        $table = (new Table('i_dont_exist_please_create_me_first'))->toArray();

        $response = $this->put(route('voyager.database.update', $table['oldName']), [
            'table' => json_encode($table),
        ]);

        // The controller catches the exception and flashes an alert instead of
        // performing the update.
        $this->assertFalse(SchemaManager::tableExists($table['name']));
        $response->assertSessionHas('alerts');
    }

    protected function can_add_column(): void
    {
        $dbTable = SchemaManager::listTableDetails($this->table['name']);

        $column = 'new_voyager_column';
        $dbTable->addColumn($column, 'text', [
            'notnull' => false,
        ]);

        $dbTable = $this->update_table($dbTable->toArray());

        $this->assertTrue($dbTable->hasColumn($column));
        $this->assertEquals('text', $dbTable->getColumn($column)->getType()->getName());
    }

    protected function can_change_column_type(): void
    {
        $column = 1;
        $columnName = $this->table['columns'][$column]['name'];
        // Request a "string" column; native introspection reports the concrete
        // SQL type ("varchar") rather than the logical alias we submitted.
        $newType = 'string';
        $oldType = $this->table['columns'][$column]['type']['name'];

        $this->assertNotEquals($oldType, $newType);

        $this->table['columns'][$column]['type']['name'] = $newType;

        $dbTable = $this->update_table($this->table);

        $this->assertEquals('varchar', $dbTable->getColumn($columnName)->getType()->getName());
    }

    protected function can_change_column_options(): void
    {
        $column = 1;
        $columnName = $this->table['columns'][$column]['name'];

        $notnull = false;
        $default = 'voyager admin';

        $this->table['columns'][$column]['notnull'] = $notnull;
        $this->table['columns'][$column]['default'] = $default;

        $dbTable = $this->update_table($this->table);
        $col = $dbTable->getColumn($columnName);

        $this->assertEquals($notnull, $col->getNotnull());
        $this->assertEquals($default, $col->getDefault());
    }

    protected function can_add_index(): void
    {
        $dbTable = SchemaManager::listTableDetails($this->table['name']);

        $indexName = 'details_unique';
        $dbTable->addUniqueIndex(['details'], $indexName);

        $dbTable = $this->update_table($dbTable->toArray());

        $this->assertTrue($dbTable->hasIndex($indexName));
        $this->assertTrue($dbTable->getIndex($indexName)->isUnique());
    }

    protected function can_rename_column(): void
    {
        $column = 1;
        $oldColumn = $this->table['columns'][$column]['oldName'];
        $newColumn = 'details_renamed_test';
        $this->table['columns'][$column]['name'] = $newColumn;

        $dbTable = $this->update_table($this->table);

        $this->assertFalse($dbTable->hasColumn($oldColumn));
        $this->assertTrue($dbTable->hasColumn($newColumn));
    }

    protected function can_drop_column(): void
    {
        $column = 1;
        $columnName = $this->table['columns'][$column]['name'];

        $dbTable = SchemaManager::listTableDetails($this->table['name']);
        $this->assertTrue($dbTable->hasColumn($columnName));

        // Drop any index referencing the column first (SQLite/MySQL parity).
        foreach ($this->table['indexes'] as $i => $index) {
            if (in_array($columnName, $index['columns'])) {
                unset($this->table['indexes'][$i]);
            }
        }
        $this->table['indexes'] = array_values($this->table['indexes']);

        unset($this->table['columns'][$column]);
        $this->table['columns'] = array_values($this->table['columns']);

        $dbTable = $this->update_table($this->table);

        $this->assertFalse($dbTable->hasColumn($columnName));
    }

    protected function can_rename_table(): void
    {
        $this->table['name'] = 'table_new_name_test';

        $this->update_table($this->table);

        $this->assertFalse(SchemaManager::tableExists($this->table['oldName']));
        $this->assertTrue(SchemaManager::tableExists($this->table['name']));
    }

    protected function update_table(array $table): Table
    {
        $response = $this->put(route('voyager.database.update', $table['oldName']), [
            'table' => json_encode($table),
        ]);

        $response->assertSessionHas('alerts');
        $response->assertRedirect(route('voyager.database.index'));

        return SchemaManager::listTableDetails($table['name']);
    }
}
