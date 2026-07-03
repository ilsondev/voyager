<?php

namespace TCG\Voyager\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use TCG\Voyager\Models\Role;
use TCG\Voyager\Models\User;

class AdminCommandTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Regression test: role_id must remain nullable so `voyager:admin --create`
     * can persist the user before the admin role is assigned.
     *
     * @return void
     */
    public function testCreateAdminAssignsAdminRole()
    {
        $this->artisan('voyager:admin', ['email' => 'newadmin@example.com', '--create' => true])
            ->expectsQuestion('Enter the admin name', 'New Admin')
            ->expectsQuestion('Enter admin password', 'password')
            ->expectsQuestion('Confirm Password', 'password')
            ->assertExitCode(0);

        $user = User::where('email', 'newadmin@example.com')->firstOrFail();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->assertEquals($adminRole->id, $user->role_id);
    }
}
