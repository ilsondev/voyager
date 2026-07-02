<?php

namespace TCG\Voyager\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use TCG\Voyager\Models\User;

class UserProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected $editPageForTheCurrentUser;

    protected $listOfUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = Auth::loginUsingId(1);

        $this->editPageForTheCurrentUser = route('voyager.users.edit', [$this->user->id]);

        $this->listOfUsers = route('voyager.users.index');
    }

    public function testCanSeeTheUserInfoOnHisProfilePage()
    {
        $this->get(route('voyager.profile'))
             ->assertSee($this->user->name)
             ->assertSee($this->user->email)
             ->assertSee(__('voyager::profile.edit'));
    }

    public function testCanEditUserName()
    {
        // The edit page is reachable and renders the edit form.
        $this->get($this->editPageForTheCurrentUser)
             ->assertOk()
             ->assertSee(__('voyager::profile.edit_user'));

        $this->put(route('voyager.users.update', [$this->user->id]), [
                 'name'  => 'New Awesome Name',
                 'email' => $this->user->email,
             ])
             ->assertRedirect($this->listOfUsers);

        $this->assertDatabaseHas('users', ['name' => 'New Awesome Name']);
    }

    public function testCanEditUserEmail()
    {
        $this->get($this->editPageForTheCurrentUser)
             ->assertOk()
             ->assertSee(__('voyager::profile.edit_user'));

        $this->put(route('voyager.users.update', [$this->user->id]), [
                 'name'  => $this->user->name,
                 'email' => 'another@email.com',
             ])
             ->assertRedirect($this->listOfUsers);

        $this->assertDatabaseHas('users', ['email' => 'another@email.com']);
    }

    public function testCanEditUserPassword()
    {
        $this->get($this->editPageForTheCurrentUser)
             ->assertOk()
             ->assertSee(__('voyager::profile.edit_user'));

        $this->put(route('voyager.users.update', [$this->user->id]), [
                 'name'     => $this->user->name,
                 'email'    => $this->user->email,
                 'password' => 'voyager-rocks',
             ])
             ->assertRedirect($this->listOfUsers);

        $updatedPassword = DB::table('users')->where('id', 1)->first()->password;
        $this->assertTrue(Hash::check('voyager-rocks', $updatedPassword));
    }

    public function testCanEditUserAvatar()
    {
        $this->get($this->editPageForTheCurrentUser)
             ->assertOk()
             ->assertSee(__('voyager::profile.edit_user'));

        $avatar = new UploadedFile(
            $this->newImagePath(),
            'new_avatar.png',
            'image/png',
            null,
            true
        );

        $this->put(route('voyager.users.update', [$this->user->id]), [
                 'name'   => $this->user->name,
                 'email'  => $this->user->email,
                 'avatar' => $avatar,
             ])
             ->assertRedirect($this->listOfUsers);

        $this->assertDatabaseMissing('users', ['id' => 1, 'avatar' => 'user/default.png']);
    }

    public function testCanEditUserEmailWithEditorPermissions()
    {
        $user = \TCG\Voyager\Models\User::factory()->for(\TCG\Voyager\Models\Role::factory())->create();
        // add permissions which reflect a possible editor role
        // without permissions to edit  users
        $user->role->permissions()->attach(\TCG\Voyager\Models\Permission::whereIn('key', [
            'browse_admin',
            'browse_users',
        ])->get()->pluck('id')->all());
        Auth::onceUsingId($user->id);

        $this->get(route('voyager.profile'))
             ->assertOk()
             ->assertSee(__('voyager::profile.edit'));

        $this->put(route('voyager.users.update', [$user->id]), [
                 'name'  => $user->name,
                 'email' => 'another@email.com',
             ])
             ->assertRedirect($this->listOfUsers);

        $this->assertDatabaseHas('users', ['email' => 'another@email.com']);
    }

    public function testCanSetUserLocale()
    {
        $this->put(route('voyager.users.update', [$this->user->id]), [
                 'name'   => $this->user->name,
                 'email'  => $this->user->email,
                 'locale' => 'de',
             ]);

        $user = User::find(1);
        $this->assertTrue(($user->locale == 'de'));

        // Validate that app()->setLocale() is called
        Auth::loginUsingId($user->id);
        $this->get(route('voyager.dashboard'));
        $this->assertTrue(($user->locale == $this->app->getLocale()));
    }

    public function testRedirectBackAfterEditWithoutBrowsePermission()
    {
        $user = User::find(1);

        // Remove `browse_users` permission
        $user->role->permissions()->detach(
            $user->role->permissions()->where('key', 'browse_users')->first()
        );

        // Without browse permission the controller redirects back; emulate the
        // browser referer that the edit form would have set.
        $this->from($this->editPageForTheCurrentUser)
             ->put(route('voyager.users.update', [$user->id]), [
                 'name'  => $user->name,
                 'email' => $user->email,
             ])
             ->assertRedirect($this->editPageForTheCurrentUser);
    }

    protected function newImagePath()
    {
        return realpath(__DIR__.'/temp/new_avatar.png');
    }
}
