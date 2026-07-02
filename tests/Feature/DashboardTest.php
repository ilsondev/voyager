<?php

namespace TCG\Voyager\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->install();
    }

    /**
     * Test Dashboard Widgets.
     *
     * This test will make sure the configured widgets are being shown on
     * the dashboard page.
     */
    public function testWidgetsAreBeingShownOnDashboardPage()
    {
        // We must first login and visit the dashboard page.
        Auth::loginUsingId(1);

        $dashboard = $this->get(route('voyager.dashboard'));
        $dashboard->assertSee(__('voyager::generic.dashboard'));

        // The three default dimmer widgets are shown, each linking to its
        // browse page.
        $dashboard->assertSee(trans_choice('voyager::dimmer.user', 1));
        $dashboard->assertSee(__('voyager::dimmer.user_link_text'));
        $dashboard->assertSee(trans_choice('voyager::dimmer.post', 4));
        $dashboard->assertSee(__('voyager::dimmer.post_link_text'));
        $dashboard->assertSee(trans_choice('voyager::dimmer.page', 1));
        $dashboard->assertSee(__('voyager::dimmer.page_link_text'));

        // The pages the widgets link to are reachable.
        $this->get(route('voyager.users.index'))->assertOk();
        $this->get(route('voyager.posts.index'))->assertOk();
        $this->get(route('voyager.pages.index'))->assertOk();
    }

    /**
     * UserDimmer widget isn't displayed without the right permissions.
     */
    public function testUserDimmerWidgetIsNotShownWithoutTheRightPermissions()
    {
        // We must first login and visit the dashboard page.
        $user = \Auth::loginUsingId(1);

        // Remove `browse_users` permission
        $user->role->permissions()->detach(
            $user->role->permissions()->where('key', 'browse_users')->first()
        );

        $this->get(route('voyager.dashboard'))
            ->assertSee(__('voyager::generic.dashboard'))
            // Test UserDimmer widget
            ->assertDontSee('<h4>1 '.trans_choice('voyager::dimmer.user', 1).'</h4>', false)
            ->assertDontSee(__('voyager::dimmer.user_link_text'));
    }

    /**
     * PostDimmer widget isn't displayed without the right permissions.
     */
    public function testPostDimmerWidgetIsNotShownWithoutTheRightPermissions()
    {
        // We must first login and visit the dashboard page.
        $user = \Auth::loginUsingId(1);

        // Remove `browse_users` permission
        $user->role->permissions()->detach(
            $user->role->permissions()->where('key', 'browse_posts')->first()
        );

        $this->get(route('voyager.dashboard'))
            ->assertSee(__('voyager::generic.dashboard'))
            // Test PostDimmer widget
            ->assertDontSee('<h4>1 '.trans_choice('voyager::dimmer.post', 1).'</h4>', false)
            ->assertDontSee(__('voyager::dimmer.post_link_text'));
    }

    /**
     * PageDimmer widget isn't displayed without the right permissions.
     */
    public function testPageDimmerWidgetIsNotShownWithoutTheRightPermissions()
    {
        // We must first login and visit the dashboard page.
        $user = \Auth::loginUsingId(1);

        // Remove `browse_users` permission
        $user->role->permissions()->detach(
            $user->role->permissions()->where('key', 'browse_pages')->first()
        );

        $this->get(route('voyager.dashboard'))
            ->assertSee(__('voyager::generic.dashboard'))
            // Test PageDimmer widget
            ->assertDontSee('<h4>1 '.trans_choice('voyager::dimmer.page', 1).'</h4>', false)
            ->assertDontSee(__('voyager::dimmer.page_link_text'));
    }

    /**
     * Test See Correct Footer Version Number.
     *
     * This test will make sure the footer contains the correct version number.
     */
    public function testSeeingCorrectFooterVersionNumber()
    {
        // We must first login and visit the dashboard page.
        Auth::loginUsingId(1);

        $this->get(route('voyager.dashboard'))
            ->assertOk()
            ->assertSee(Voyager::getVersion());
    }
}
