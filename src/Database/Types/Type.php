<?php

namespace TCG\Voyager\Database\Types;

use TCG\Voyager\Database\Platforms\Platform;
use TCG\Voyager\Database\Schema\SchemaManager;

/**
 * Base class for Voyager's database "types".
 *
 * Historically this extended Doctrine DBAL's Type class. Laravel removed the
 * built-in Doctrine DBAL integration in Laravel 11, so this is now a plain
 * value object whose sole responsibilities are:
 *   - exposing a canonical type NAME (used to build the column-type dropdown
 *     in the database manager UI, and to map to Laravel Blueprint column types);
 *   - carrying UI metadata ("custom options") such as the category the type
 *     belongs to, whether it is supported, whether it can be indexed, etc.
 *
 * The old getSQLDeclaration()/Doctrine platform hooks are gone: schema
 * creation and alteration are now performed through Laravel's Blueprint /
 * native Schema builder instead of Doctrine's SQL generation.
 */
abstract class Type
{
    protected static $customTypesRegistered = false;
    protected static $platformTypes = [];
    protected static $customTypeOptions = [];
    protected static $allTypes = [];
    protected static $typeCategories = [];

    /**
     * Registered types for the current platform, keyed by their NAME.
     *
     * @var array<string, string> map of type name => fully-qualified class name
     */
    protected static $registeredTypes = [];

    public const NAME = 'UNDEFINED_TYPE_NAME';
    public const NOT_SUPPORTED = 'notSupported';
    public const NOT_SUPPORT_INDEX = 'notSupportIndex';

    /**
     * Per-instance UI options (category, default input config, etc.).
     *
     * @var array
     */
    public $customOptions = [];

    /**
     * The table this type instance is associated with, when known.
     *
     * @var string|null
     */
    public $tableName;

    public function getName()
    {
        return static::NAME;
    }

    /**
     * Export a type (instance or already-array) to its array representation.
     *
     * @param self|array $type
     *
     * @return array
     */
    public static function toArray($type)
    {
        if (is_array($type)) {
            $name = $type['name'] ?? ($type[static::class] ?? null);
            $customTypeOptions = $type;
            unset($customTypeOptions['name']);

            return array_merge(['name' => $name], $customTypeOptions);
        }

        $customTypeOptions = $type->customOptions ?? [];

        return array_merge([
            'name' => $type->getName(),
        ], $customTypeOptions);
    }

    /**
     * Build the list of available types for the current database platform,
     * grouped by category, ready to be consumed by the database-manager UI.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getPlatformTypes()
    {
        if (static::$platformTypes) {
            return static::$platformTypes;
        }

        if (!static::$customTypesRegistered) {
            static::registerCustomPlatformTypes();
        }

        $platform = ucfirst(SchemaManager::getDatabaseConnection()->getDriverName());

        // Collection of type name => class name for every registered type.
        $typeMapping = collect(static::$registeredTypes);

        // Let the platform prune the types it doesn't want to expose.
        $typeMapping = Platform::getPlatformTypes($platform, $typeMapping);

        static::$platformTypes = $typeMapping
            ->map(function ($typeClass, $name) {
                $type = new $typeClass();
                $type->customOptions = static::resolveCustomOptions($name);

                return static::toArray($type);
            })
            ->filter(function ($type) {
                // Only expose types we managed to categorise.
                return !empty($type['category']);
            })
            ->groupBy('category');

        return static::$platformTypes;
    }

    public static function registerCustomPlatformTypes($force = false)
    {
        if (static::$customTypesRegistered && !$force) {
            return;
        }

        // Reset state so a forced re-registration (e.g. switching connection in
        // tests) starts from a clean slate.
        if ($force) {
            static::$platformTypes = [];
            static::$customTypeOptions = [];
            static::$allTypes = [];
            static::$typeCategories = [];
            static::$registeredTypes = [];
        }

        $platform = SchemaManager::getDatabaseConnection()->getDriverName();
        $platformName = ucfirst($platform);

        $customTypes = array_merge(
            static::getPlatformCustomTypes('Common'),
            static::getPlatformCustomTypes($platformName)
        );

        foreach ($customTypes as $type) {
            $name = $type::NAME;
            static::registerType($name, $type);
        }

        static::addCustomTypeOptions($platformName);

        static::$customTypesRegistered = true;
    }

    protected static function addCustomTypeOptions($platformName)
    {
        static::registerCommonCustomTypeOptions();

        Platform::registerPlatformCustomTypeOptions($platformName);
    }

    /**
     * Resolve the merged custom options for a given type name.
     *
     * @param string $typeName
     *
     * @return array
     */
    protected static function resolveCustomOptions($typeName)
    {
        $options = [];

        foreach (static::$customTypeOptions as $option) {
            if (in_array($typeName, $option['types'], true)) {
                $options[$option['name']] = $option['value'];
            }
        }

        return $options;
    }

    protected static function getPlatformCustomTypes($platformName)
    {
        $typesPath = __DIR__.DIRECTORY_SEPARATOR.$platformName.DIRECTORY_SEPARATOR;
        $namespace = __NAMESPACE__.'\\'.$platformName.'\\';
        $types = [];

        foreach (glob($typesPath.'*.php') as $classFile) {
            $types[] = $namespace.str_replace(
                '.php',
                '',
                str_replace($typesPath, '', $classFile)
            );
        }

        return $types;
    }

    public static function registerCustomOption($name, $value, $types)
    {
        if (is_string($types)) {
            $types = trim($types);

            if ($types == '*') {
                $types = static::getAllTypes()->toArray();
            } elseif (strpos($types, '*') !== false) {
                $searchType = str_replace('*', '', $types);
                $types = static::getAllTypes()->filter(function ($type) use ($searchType) {
                    return strpos($type, $searchType) !== false;
                })->values()->toArray();
            } else {
                $types = [$types];
            }
        }

        static::$customTypeOptions[] = [
            'name'  => $name,
            'value' => $value,
            'types' => $types,
        ];
    }

    protected static function registerCommonCustomTypeOptions()
    {
        static::registerTypeCategories();
        static::registerTypeDefaultOptions();
    }

    protected static function registerTypeDefaultOptions()
    {
        $types = static::getTypeCategories();

        // Numbers
        static::registerCustomOption('default', [
            'type' => 'number',
            'step' => 'any',
        ], $types['numbers']);

        // Date and Time
        static::registerCustomOption('default', [
            'type' => 'date',
        ], 'date');
        static::registerCustomOption('default', [
            'type' => 'time',
            'step' => '1',
        ], 'time');
        static::registerCustomOption('default', [
            'type' => 'number',
            'min'  => '0',
        ], 'year');
    }

    protected static function registerTypeCategories()
    {
        $types = static::getTypeCategories();

        static::registerCustomOption('category', 'Numbers', $types['numbers']);
        static::registerCustomOption('category', 'Strings', $types['strings']);
        static::registerCustomOption('category', 'Date and Time', $types['datetime']);
        static::registerCustomOption('category', 'Lists', $types['lists']);
        static::registerCustomOption('category', 'Binary', $types['binary']);
        static::registerCustomOption('category', 'Geometry', $types['geometry']);
        static::registerCustomOption('category', 'Network', $types['network']);
        static::registerCustomOption('category', 'Objects', $types['objects']);
    }

    public static function getAllTypes()
    {
        if (static::$allTypes) {
            return static::$allTypes;
        }

        static::$allTypes = collect(static::getTypeCategories())->flatten();

        return static::$allTypes;
    }

    public static function getTypeCategories()
    {
        if (static::$typeCategories) {
            return static::$typeCategories;
        }

        $numbers = [
            'boolean',
            'tinyint',
            'smallint',
            'mediumint',
            'integer',
            'int',
            'bigint',
            'decimal',
            'numeric',
            'money',
            'float',
            'real',
            'double',
            'double precision',
        ];

        $strings = [
            'char',
            'character',
            'varchar',
            'character varying',
            'string',
            'guid',
            'uuid',
            'tinytext',
            'text',
            'mediumtext',
            'longtext',
            'tsquery',
            'tsvector',
            'xml',
        ];

        $datetime = [
            'date',
            'datetime',
            'year',
            'time',
            'timetz',
            'timestamp',
            'timestamptz',
            'datetimetz',
            'dateinterval',
            'interval',
        ];

        $lists = [
            'enum',
            'set',
            'simple_array',
            'array',
            'json',
            'jsonb',
            'json_array',
        ];

        $binary = [
            'bit',
            'bit varying',
            'binary',
            'varbinary',
            'tinyblob',
            'blob',
            'mediumblob',
            'longblob',
            'bytea',
        ];

        $network = [
            'cidr',
            'inet',
            'macaddr',
            'txid_snapshot',
        ];

        $geometry = [
            'geometry',
            'point',
            'linestring',
            'polygon',
            'multipoint',
            'multilinestring',
            'multipolygon',
            'geometrycollection',
        ];

        $objects = [
            'object',
        ];

        static::$typeCategories = [
            'numbers'  => $numbers,
            'strings'  => $strings,
            'datetime' => $datetime,
            'lists'    => $lists,
            'binary'   => $binary,
            'network'  => $network,
            'geometry' => $geometry,
            'objects'  => $objects,
        ];

        return static::$typeCategories;
    }

    public static function registerType($name, $typeClass)
    {
        static::$registeredTypes[$name] = $typeClass;
    }

    public static function hasType($name)
    {
        return isset(static::$registeredTypes[$name]);
    }

    /**
     * Get a fresh instance of a registered type by name, with its UI options
     * already resolved.
     *
     * @param string $name
     *
     * @return static
     */
    public static function getType($name)
    {
        if (!static::hasType($name)) {
            throw new \InvalidArgumentException("Unknown database type [{$name}].");
        }

        $class = static::$registeredTypes[$name];
        /** @var self $type */
        $type = new $class();
        $type->customOptions = static::resolveCustomOptions($name);

        return $type;
    }
}
