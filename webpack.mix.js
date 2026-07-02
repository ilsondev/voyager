let mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.options({ processCssUrls: false }).sass('resources/assets/sass/app.scss', 'publishable/assets/css')
// runtimeOnly must stay false (the default): most Vue components in this codebase are
// registered with a runtime `template:` string pulled from a Blade @yield section rather
// than a precompiled .vue SFC, so the shipped Vue build must include the runtime compiler.
.js('resources/assets/js/app.js', 'publishable/assets/js').vue({ version: 3 })
.copy('node_modules/tinymce/skins', 'publishable/assets/js/skins')
.copy('resources/assets/js/skins', 'publishable/assets/js/skins')
.copy('node_modules/tinymce/themes/silver', 'publishable/assets/js/themes/silver')
.copy('node_modules/tinymce/models/dom', 'publishable/assets/js/models/dom')
.copy('node_modules/tinymce/icons/default', 'publishable/assets/js/icons/default')
.copy('node_modules/ace-builds/src-noconflict', 'publishable/assets/js/ace/libs');
