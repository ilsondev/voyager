<?php

namespace TCG\Voyager\Tests;

use Illuminate\Support\Facades\Auth;

class LoginTest extends TestCase
{
    public function testSuccessfulLoginWithDefaultCredentials()
    {
        $response = $this->post(route('voyager.postlogin'), [
            'email'    => 'admin@admin.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('voyager.dashboard'));
        $this->assertTrue(Auth::guard(app('VoyagerGuard'))->check());
    }

    public function testShowAnErrorMessageWhenITryToLoginWithWrongCredentials()
    {
        session()->setPreviousUrl(route('voyager.login'));

        $response = $this->post(route('voyager.postlogin'), [
            'email'    => 'john@Doe.com',
            'password' => 'pass',
        ]);

        $response->assertRedirect(route('voyager.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest(app('VoyagerGuard'));

        // The failed-login error message and the old input are flashed back so
        // the login form can re-render them.
        $this->assertEquals(__('auth.failed'), session('errors')->first('email'));
        $this->assertEquals('john@Doe.com', old('email'));
    }

    public function testRedirectIfLoggedIn()
    {
        Auth::loginUsingId(1);

        $this->get(route('voyager.login'))
             ->assertRedirect(route('voyager.dashboard'));
    }

    public function testRedirectIfNotLoggedIn()
    {
        $this->get(route('voyager.profile'))
             ->assertRedirect(route('voyager.login'));
    }

    public function testCanLogout()
    {
        Auth::loginUsingId(1);

        $this->post(route('voyager.logout'))
             ->assertRedirect(route('voyager.login'));

        $this->assertGuest(app('VoyagerGuard'));
    }

    public function testGetsLockedOutAfterFiveAttempts()
    {
        session()->setPreviousUrl(route('voyager.login'));

        $response = null;
        for ($i = 0; $i <= 6; $i++) {
            $response = $this->post(route('voyager.postlogin'), [
                'email'    => 'john@Doe.com',
                'password' => 'pass',
            ]);
        }

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Too many login attempts. Please try again in',
            session('errors')->first('email')
        );
    }
}
