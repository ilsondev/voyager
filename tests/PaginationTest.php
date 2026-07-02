<?php

namespace TCG\Voyager\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Post;

class PaginationTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = Auth::loginUsingId(1);
    }

    /**
     * Renders a real, server-side paginated BREAD listing and asserts that
     * Bootstrap pagination markup (produced through Paginator::useBootstrap())
     * is emitted. This guards against the Laravel 13 internal pagination view
     * rename (pagination::default -> pagination::bootstrap-3), which Voyager
     * relies on transparently via the native useBootstrap() helper.
     */
    public function testServerSidePaginationRendersBootstrapLinks()
    {
        // Enable server-side pagination for the posts BREAD so the controller
        // uses paginate() (LengthAwarePaginator) and the view renders ->links().
        $dataType = DataType::where('slug', 'posts')->firstOrFail();
        $dataType->server_side = 1;
        $dataType->save();

        // The dummy seeder creates 4 posts. Add enough to exceed the default
        // page length of 15 so there is a genuine second page with links.
        for ($i = 0; $i < 20; $i++) {
            Post::create([
                'author_id'        => 0,
                'title'            => 'Pagination Post '.$i,
                'excerpt'          => 'excerpt '.$i,
                'body'             => '<p>body '.$i.'</p>',
                'slug'             => 'pagination-post-'.$i,
                'meta_description' => 'meta '.$i,
                'meta_keywords'    => 'k1, k2',
                'status'           => 'PUBLISHED',
                'featured'         => 0,
            ]);
        }

        $response = $this->get(route('voyager.posts.index'));

        $response->assertOk();

        // Bootstrap-3 pagination markup, produced through Paginator::useBootstrap().
        // In Laravel 13 the internal view was renamed (pagination::default ->
        // pagination::bootstrap-3); asserting the Bootstrap "pagination" ul class
        // proves the Bootstrap view (not the default Tailwind one) rendered.
        $response->assertSee('<ul class="pagination', false);
        // A concrete link to the second page must be present.
        $response->assertSee('page=2', false);
        // The server-side "showing entries" block only renders when paginating.
        $response->assertSee('show-res', false);
    }
}
