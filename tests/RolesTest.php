<?php

namespace TCG\Voyager\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\Role;

class RolesTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $permission_id = 3;

    public function setUp(): void
    {
        parent::setUp();

        Auth::loginUsingId(1);
        $this->user = Auth::user();
    }

    /**
     * A basic functional test example.
     *
     * @return void
     */
    public function testRoles()
    {
        // Adding a New Role
        $this->post(route('voyager.roles.store'), [
                 'name'         => 'superadmin',
                 'display_name' => 'Super Admin',
             ])
             ->assertRedirect(route('voyager.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'superadmin']);

        // Editing a Role
        $role = Role::find(2);
        $this->put(route('voyager.roles.update', 2), [
                 'name'         => 'regular_user',
                 'display_name' => $role->display_name,
             ])
             ->assertRedirect(route('voyager.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'regular_user']);

        // Editing a Role
        $this->put(route('voyager.roles.update', 2), [
                 'name'         => 'user',
                 'display_name' => $role->display_name,
             ])
             ->assertRedirect(route('voyager.roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'user']);

        // Get the current super admin role
        $superadmin_role = Role::where('name', '=', 'superadmin')->first();

        // Deleting a Role
        $response = $this->call('DELETE', route('voyager.roles.destroy', $superadmin_role->id), ['_token' => csrf_token()]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertDatabaseMissing('roles', ['name' => 'superadmin']);
    }

    /**
     * Edit role permissions.
     *
     * @return void
     */
    public function testEditRolePermissions()
    {
        $this->assertDatabaseMissing('permission_role', ['permission_id' => $this->permission_id, 'role_id' => 2]);

        $role = Role::find(2);
        $role->permissions()->attach($this->permission_id);
        $this->assertDatabaseHas('permission_role', ['permission_id' => $this->permission_id, 'role_id' => 2]);

        // Submitting the edit form with the permission unchecked means it is
        // simply absent from the posted `permissions` array; the controller
        // syncs to whatever is submitted, dropping the missing permission.
        $permissions = $role->permissions()
            ->pluck('permissions.id')
            ->reject(fn ($id) => $id == $this->permission_id)
            ->values()
            ->all();

        $this->put(route('voyager.roles.update', 2), [
                 'name'         => $role->name,
                 'display_name' => $role->display_name,
                 'permissions'  => $permissions,
             ])
             ->assertRedirect(route('voyager.roles.index'));

        $this->assertDatabaseMissing('permission_role', ['permission_id' => $this->permission_id, 'role_id' => 2]);
    }
}
