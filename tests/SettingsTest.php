<?php

namespace TCG\Voyager\Tests;

use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Setting;

class SettingsTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = Auth::loginUsingId(1);
        session()->setPreviousUrl(route('voyager.settings.index'));
    }

    public function testCanUpdateSettings()
    {
        $key = 'site.title';
        $newTitle = 'Just Another LaravelVoyager.com Site';

        $setting = Setting::where('key', '=', $key)->first();

        // The settings index shows the current value and a save button.
        $this->get(route('voyager.settings.index'))
             ->assertSee($setting->value)
             ->assertSee(__('voyager::settings.save'));

        // Settings are updated in bulk; each field is posted as the key with
        // dots replaced by underscores, plus a `<key>_group` companion field.
        // The controller rebuilds every setting's key from these, so all
        // settings must be present to preserve their groups/values.
        $payload = [];
        foreach (Setting::all() as $s) {
            $field = str_replace('.', '_', $s->key);
            $payload[$field] = $s->value;
            $payload[$field.'_group'] = $s->group;
        }
        $payload[str_replace('.', '_', $key)] = $newTitle;

        $this->put(route('voyager.settings.update'), $payload)
             ->assertRedirect(route('voyager.settings.index'));

        $this->assertDatabaseHas('settings', [
            'key'   => $key,
            'value' => $newTitle,
        ]);
    }

    public function testCanCreateSetting()
    {
        $this->post(route('voyager.settings.store'), [
                 'display_name' => 'New Setting',
                 'key'          => 'new_setting',
                 'type'         => 'text',
                 'group'        => 'Site',
             ])
             ->assertRedirect(route('voyager.settings.index'));

        $this->assertDatabaseHas('settings', [
            'display_name' => 'New Setting',
            'key'          => 'site.new_setting',
            'type'         => 'text',
            'group'        => 'Site',
        ]);
    }

    public function testCanDeleteSetting()
    {
        $setting = Setting::firstOrFail();

        $this->call('DELETE', route('voyager.settings.delete', $setting->id));

        $this->assertDatabaseMissing('settings', [
            'id'    => $setting->id,
        ]);
    }

    public function testCanDeleteSettingsValue()
    {
        $setting = Setting::firstOrFail();
        $this->assertFalse(Setting::find($setting->id)->value == null);

        $this->call('PUT', route('voyager.settings.delete_value', $setting->id));

        $this->assertDatabaseHas('settings', [
            'id'    => $setting->id,
            'value' => '',
        ]);
    }

    public function testCanMoveSettingUp()
    {
        $setting = Setting::where('order', '!=', 1)->first();

        $this->call('GET', route('voyager.settings.move_up', $setting->id));

        $this->assertDatabaseHas('settings', [
            'id'    => $setting->id,
            'order' => ($setting->order - 1),
        ]);
    }

    public function testCanMoveSettingDown()
    {
        $setting = Setting::where('order', '!=', 1)->first();

        $this->call('GET', route('voyager.settings.move_down', $setting->id));

        $this->assertDatabaseHas('settings', [
            'id'    => $setting->id,
            'order' => ($setting->order + 1),
        ]);
    }
}
