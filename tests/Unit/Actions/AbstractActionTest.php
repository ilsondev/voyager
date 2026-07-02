<?php

namespace TCG\Voyager\Tests\Unit\Actions;

use TCG\Voyager\Actions\AbstractAction;
use TCG\Voyager\Facades\Voyager;
use TCG\Voyager\Models\User;
use TCG\Voyager\Tests\TestCase;

class AbstractActionTest extends TestCase
{
    /**
     * The users DataType instance.
     *
     * @var \TCG\Voyager\Models\DataType
     */
    protected $userDataType;

    /**
     * A dummy user instance.
     *
     * @var \TCG\Voyager\Models\User
     */
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $role = \TCG\Voyager\Models\Role::create(['name' => 'test_role', 'display_name' => 'Test Role']);
        $this->userDataType = Voyager::model('DataType')->where('name', 'users')->first();
        $this->user = \TCG\Voyager\Models\User::factory()->create();
    }

    /**
     * `AbstractAction` doesn't implement `getTitle`/`getIcon`/`getDefaultRoute`
     * from `ActionInterface`, so any concrete stand-in needs to supply them.
     * PHPUnit 12 removed `getMockForAbstractClass()` (which used to auto-stub
     * these) with no direct replacement, and plain `getMockBuilder()->getMock()`
     * does not auto-implement interface methods left unimplemented by an
     * abstract class - it fatals with "must implement the remaining methods".
     * A real anonymous subclass sidesteps the mock generator entirely and
     * exercises the actual dynamic-dispatch behavior in `getRoute()`.
     */
    protected function makeAction($dataType, $user, array $overrides = [])
    {
        return new class($dataType, $user, $overrides) extends AbstractAction {
            protected $overrides;

            public function __construct($dataType, $data, array $overrides)
            {
                parent::__construct($dataType, $data);
                $this->overrides = $overrides;
            }

            public function getTitle()
            {
                return 'Title';
            }

            public function getIcon()
            {
                return 'icon';
            }

            public function getDefaultRoute()
            {
                return $this->overrides['getDefaultRoute'] ?? false;
            }

            public function getAttributes()
            {
                return $this->overrides['getAttributes'] ?? parent::getAttributes();
            }

            public function getDataType()
            {
                return array_key_exists('getDataType', $this->overrides)
                    ? $this->overrides['getDataType']
                    : parent::getDataType();
            }
        };
    }

    /**
     * This test checks that `getRoute` method calls the `getDefaultRoute`
     * method if the given key is empty.
     */
    public function testGetRouteWithEmptyKey()
    {
        $stub = $this->makeAction(null, null, ['getDefaultRoute' => true]);

        $this->assertTrue($stub->getRoute($this->userDataType->name));
    }

    /**
     * This test checks that `getRoute` method calls the expected method when a
     * key is given.
     */
    public function testGetRouteWithCustomKey()
    {
        $stub = new class(null, null) extends AbstractAction {
            public function getTitle()
            {
                return 'Custom';
            }

            public function getIcon()
            {
                return 'icon';
            }

            public function getDefaultRoute()
            {
                return false;
            }

            public function getCustomRoute()
            {
                return true;
            }
        };

        // The key that's passed to the `getRoute` method will be capitalized
        // and putted between 'get' and 'Route'. Calling `getRoute('custom')`
        // will call the `getCustomRoute` method if it's defined.
        $this->assertTrue($stub->getRoute('custom'));
    }

    /**
     * This test checks that `getAttributes` method will give us the expected
     * output.
     */
    public function testConvertAttributesToHtml()
    {
        $stub = $this->makeAction(null, null, [
            'getAttributes' => [
                'class'   => 'class1 class2',
                'data-id' => 5,
                'id'      => 'delete-5',
            ],
        ]);

        $this->assertEquals('class="class1 class2" data-id="5" id="delete-5"', $stub->convertAttributesToHtml());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns true
     * if the action should be displayed for every data type.
     */
    public function testShouldActionDisplayOnDataTypeWithDefaultDataType()
    {
        $stub = $this->makeAction($this->userDataType, $this->user);

        $this->assertTrue($stub->shouldActionDisplayOnDataType());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns true
     * if the action should only be displayed for a specific data type.
     */
    public function testTrueIsReturnedIfDataTypeMatchesTheOneWhereTheActionWasCreatedFor()
    {
        $stub = $this->makeAction($this->userDataType, $this->user, [
            'getDataType' => $this->userDataType->name,
        ]);

        $this->assertTrue($stub->shouldActionDisplayOnDataType());
    }

    /**
     * This test checks that `shouldActionDisplayOnDataType` method returns false
     * if the action should only be displayed for a specific data type.
     */
    public function testFalseIsReturnedIfDataTypeDoesNotMatchesTheOneWhereTheActionWasCreatedFor()
    {
        $stub = $this->makeAction($this->userDataType, $this->user, [
            'getDataType' => 'not users', // different data type
        ]);

        $this->assertFalse($stub->shouldActionDisplayOnDataType());
    }
}
