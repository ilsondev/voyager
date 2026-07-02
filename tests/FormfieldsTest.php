<?php

namespace TCG\Voyager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use TCG\Voyager\Models\Category;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Permission;

class FormfieldsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Auth::loginUsingId(1);
    }

    public function testFormfieldText()
    {
        $this->createBreadForFormfield('text', 'text', json_encode([
            'default' => 'Default Text',
            'null'    => 'NULL',
        ]));

        // The create form shows the configured default value.
        $this->get(route('voyager.categories.create'))->assertSee('Default Text');

        $this->storeCategory(['text' => 'New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['text' => 'Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');

        // The configured NULL value is stored as an actual null.
        $this->updateCategory(1, ['text' => 'NULL'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertDatabaseHas('categories', ['text' => null]);
    }

    public function testFormfieldTextbox()
    {
        $this->createBreadForFormfield('text', 'text_area', json_encode([
            'default' => 'Default Text',
        ]));

        $this->get(route('voyager.categories.create'))->assertSee('Default Text');

        $this->storeCategory(['text_area' => 'New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['text_area' => 'Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');
    }

    public function testFormfieldCodeeditor()
    {
        $this->createBreadForFormfield('text', 'code_editor', json_encode([
            'default' => 'Default Text',
        ]));

        $this->get(route('voyager.categories.create'))->assertSee('Default Text');

        $this->storeCategory(['code_editor' => 'New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['code_editor' => 'Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');
    }

    public function testFormfieldMarkdown()
    {
        $this->createBreadForFormfield('text', 'markdown_editor');

        $this->storeCategory(['markdown_editor' => '# New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['markdown_editor' => '# Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');
    }

    public function testFormfieldRichtextbox()
    {
        $this->createBreadForFormfield('text', 'rich_text_box');

        $this->storeCategory(['rich_text_box' => 'New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['rich_text_box' => 'Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');
    }

    public function testFormfieldHidden()
    {
        $this->createBreadForFormfield('text', 'hidden', json_encode([
            'default' => 'Default Text',
        ]));

        $this->get(route('voyager.categories.create'))->assertSee('Default Text');

        $this->storeCategory(['hidden' => 'New Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('New Text');

        $this->updateCategory(1, ['hidden' => 'Edited Text'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Edited Text');
    }

    public function testFormfieldPassword()
    {
        $this->createBreadForFormfield('text', 'password');

        $this->storeCategory(['password' => 'newpassword'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertTrue(Hash::check('newpassword', Category::first()->password));

        // Submitting an empty password on edit must preserve the existing hash.
        $this->updateCategory(1, ['password' => ''])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertTrue(Hash::check('newpassword', Category::first()->password));
    }

    public function testFormfieldNumber()
    {
        $this->createBreadForFormfield('integer', 'number', json_encode([
            'default' => 1,
        ]));

        $this->get(route('voyager.categories.create'))->assertSee('1');

        $this->storeCategory(['number' => '2'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('2');

        $this->updateCategory(1, ['number' => '3'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('3');
    }

    public function testFormfieldCheckbox()
    {
        $this->createBreadForFormfield('boolean', 'checkbox', json_encode([
            'on'  => 'Active',
            'off' => 'Inactive',
        ]));

        $this->get(route('voyager.categories.create'))->assertSee('Inactive');

        // A checked checkbox posts the value "on"; unchecked posts nothing.
        $this->storeCategory(['checkbox' => 'on'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Active');

        $this->updateCategory(1, [])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Inactive');
    }

    public function testFormfieldTime()
    {
        $this->createBreadForFormfield('time', 'time');

        $this->storeCategory(['time' => '12:50'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('12:50');

        $this->updateCategory(1, ['time' => '6:25'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('6:25');
    }

    public function testFormfieldDate()
    {
        $this->createBreadForFormfield('date', 'date', json_encode([
            'format' => '%Y-%m-%d',
        ]));

        $this->storeCategory(['date' => '2019-01-01'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('2019-01-01');

        $this->updateCategory(1, ['date' => '2018-12-31'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('2018-12-31');
    }

    public function testFormfieldTimestamp()
    {
        $this->createBreadForFormfield('timestamp', 'timestamp', json_encode([
            'format' => '%F %T',
        ]));

        $this->storeCategory(['timestamp' => '2019-01-01 12:00:00'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('2019-01-01 12:00:00');

        $this->updateCategory(1, ['timestamp' => '2018-12-31 23:59:59'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('2018-12-31 23:59:59');

        // An empty timestamp is stored as null.
        $this->updateCategory(1, ['timestamp' => ''])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertDatabaseHas('categories', ['timestamp' => null]);
    }

    public function testFormfieldColor()
    {
        $this->createBreadForFormfield('text', 'color');

        $this->storeCategory(['color' => '#FF0000'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('#FF0000');

        $this->updateCategory(1, ['color' => '#00FF00'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('#00FF00');
    }

    public function testFormfieldRadiobtn()
    {
        $this->createBreadForFormfield('text', 'radio_btn', json_encode([
            'default' => 'radio1',
            'options' => [
                'radio1' => 'Foo',
                'radio2' => 'Bar',
            ],
        ]));

        $this->storeCategory(['radio_btn' => 'radio1'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Foo');

        $this->updateCategory(1, ['radio_btn' => 'radio2'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Bar');
    }

    public function testFormfieldSelectDropdown()
    {
        $this->createBreadForFormfield('text', 'select_dropdown', json_encode([
            'default' => 'radio1',
            'options' => [
                'option1' => 'Foo',
                'option2' => 'Bar',
            ],
        ]));

        $this->storeCategory(['select_dropdown' => 'option1'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Foo');

        $this->updateCategory(1, ['select_dropdown' => 'option2'])
             ->assertRedirect(route('voyager.categories.index'));
        $this->get(route('voyager.categories.index'))->assertSee('Bar');
    }

    public function testFormfieldFile()
    {
        $this->createBreadForFormfield('text', 'file');
        $file = UploadedFile::fake()->create('test.txt', 1);

        $this->storeCategory([], ['file' => [$file]])
             ->assertRedirect(route('voyager.categories.index'));

        // Storing with no file leaves an empty JSON array.
        $this->storeCategory([])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertDatabaseHas('categories', ['file' => '[]']);
    }

    public function testFormfieldFilePreserve()
    {
        $this->createBreadForFormfield('text', 'file', json_encode([
            'preserveFileUploadName' => true,
        ]));
        $file = UploadedFile::fake()->create('test.txt', 1);

        $this->storeCategory([], ['file' => [$file]])
             ->assertRedirect(route('voyager.categories.index'));

        $this->storeCategory([])
             ->assertRedirect(route('voyager.categories.index'));
        $this->assertDatabaseHas('categories', ['file' => '[]']);
    }

    /**
     * Post a new category through the BREAD data store endpoint.
     */
    protected function storeCategory(array $data, array $files = [])
    {
        return $this->call(
            'POST',
            route('voyager.categories.store'),
            $data,
            [],
            $files
        );
    }

    /**
     * Update a category through the BREAD data update endpoint.
     */
    protected function updateCategory($id, array $data, array $files = [])
    {
        return $this->call(
            'PUT',
            route('voyager.categories.update', $id),
            $data,
            [],
            $files
        );
    }

    private function createBreadForFormfield($type, $name, $options = '')
    {
        Schema::dropIfExists('categories');
        Schema::create('categories', function ($table) use ($type, $name) {
            $table->bigIncrements('id');
            $table->{$type}($name)->nullable();
            $table->timestamps();
        });

        // Delete old BREAD
        $this->delete(route('voyager.bread.delete', ['id' => DataType::where('name', 'categories')->first()->id]));

        // Create BREAD by posting the full data-type form for the categories
        // table. The store endpoint iterates over every column, so we build the
        // per-field payload for all columns and set the requested input type on
        // our target field.
        $payload = $this->breadPayload($name, $options);

        $this->post(route('voyager.bread.store'), $payload)
             ->assertRedirect(route('voyager.bread.index'));

        // Attach permissions to role
        Auth::user()->role->permissions()->syncWithoutDetaching(Permission::all()->pluck('id'));
    }

    /**
     * Build the BREAD store payload for the categories table, giving the target
     * field the requested input type/details.
     */
    private function breadPayload($fieldName, $options)
    {
        $columns = Schema::getColumnListing('categories');

        $payload = [
            'name'                  => 'categories',
            'slug'                  => 'categories',
            'display_name_singular' => 'Category',
            'display_name_plural'   => 'Categories',
            'model_name'            => 'TCG\\Voyager\\Models\\Category',
            'controller'            => '',
            'policy_name'           => '',
            'url'                   => '',
            'server_side'           => 0,
            'generate_permissions'  => 1,
            'details'               => '',
        ];

        // Auto-managed columns are visible but never editable through the form.
        $readOnly = ['id', 'created_at', 'updated_at', 'deleted_at'];

        $order = 1;
        foreach ($columns as $column) {
            $editable = !in_array($column, $readOnly);
            $inputType = $column === $fieldName ? $fieldName : 'text';
            $details = $column === $fieldName ? ($options ?: '') : '';

            $payload['field_'.$column] = $column;
            $payload['field_input_type_'.$column] = $inputType;
            $payload['field_details_'.$column] = $details;
            $payload['field_display_name_'.$column] = ucfirst(str_replace('_', ' ', $column));
            $payload['field_order_'.$column] = $order++;
            $payload['field_browse_'.$column] = 1;
            $payload['field_read_'.$column] = 1;
            if ($editable) {
                $payload['field_edit_'.$column] = 1;
                $payload['field_add_'.$column] = 1;
            }
            $payload['field_delete_'.$column] = 1;
        }

        return $payload;
    }
}
