const mix = require('laravel-mix');
/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

require('laravel-mix-tailwind');

mix.js('resources/js/main.js', 'public/js').vue({ version: 3 })
    .sass('resources/sass/app.scss', 'public/css')
    .tailwind()
    .version(); 