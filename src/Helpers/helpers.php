<?php

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return TCG\Voyager\Facades\Voyager::setting($key, $default);
    }
}

if (!function_exists('menu')) {
    function menu($menuName, $type = null, array $options = [])
    {
        return TCG\Voyager\Facades\Voyager::model('Menu')->display($menuName, $type, $options);
    }
}

if (!function_exists('voyager_asset')) {
    function voyager_asset($path, $secure = null)
    {
        $url = route('voyager.voyager_assets').'?path='.urlencode($path);

        // The asset route serves files with a 1-year max-age but the URL is
        // otherwise constant across package rebuilds/upgrades, so browsers keep
        // serving stale JS/CSS until a hard refresh. Append a content-version
        // token (the file's mtime) so the URL changes whenever the asset does,
        // making the long cache lifetime safe (cache-bust on change, cache
        // forever otherwise). Resolved against the package's own publishable
        // assets, mirroring VoyagerController@assets.
        static $versions = [];
        if (!array_key_exists($path, $versions)) {
            $file = dirname(__DIR__, 2).'/publishable/assets/'.ltrim($path, '/');
            $versions[$path] = is_file($file) ? filemtime($file) : null;
        }
        if ($versions[$path] !== null) {
            $url .= '&v='.$versions[$path];
        }

        return $url;
    }
}

if (!function_exists('get_file_name')) {
    function get_file_name($name)
    {
        preg_match('/(_)([0-9])+$/', $name, $matches);
        if (count($matches) == 3) {
            return Illuminate\Support\Str::replaceLast($matches[0], '', $name).'_'.(intval($matches[2]) + 1);
        } else {
            return $name.'_1';
        }
    }
}
