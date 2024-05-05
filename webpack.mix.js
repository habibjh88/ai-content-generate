const mix = require('laravel-mix');
const fsExtra = require('fs-extra');
const path = require("path");
const cliColor = require("cli-color");
const emojic = require("emojic");
const wpPot = require('wp-pot');
const min = mix.inProduction() ? '.min' : '';
const archiver = require('archiver');

let WebpackRTLPlugin = require( 'webpack-rtl-plugin' );
// require( "@tinypixelco/laravel-mix-wp-blocks" );
const isProduction = Mix.inProduction() ? true : false

const package_path = path.resolve(__dirname);
const package_slug = path.basename(path.resolve(package_path));
const temDirectory = package_path + '/dist';

// Autloading jQuery to make it accessible to all the packages, because, you know, reasons
// You can comment this line if you don't need jQuery.
mix.autoload({
    jquery: ['$', 'window.jQuery', 'jQuery'],
});

if ((!process.env.npm_config_block && !process.env.npm_config_package) && (process.env.NODE_ENV === 'development' || process.env.NODE_ENV === 'production')) {
    /*
    mix.options({
        postCss: [
            require("css-mqpacker") //--> merge media queries
        ],
        terser: {
            extractComments: false, //--> Prevent to generate license .txt file
        },
        processCssUrls: false,//--> Prevent to check assets to the url to copy
    });
     */

    if (mix.inProduction()) {
        let languages = path.resolve('languages');
        fsExtra.ensureDir(languages, function (err) {
            if (err) return console.error(err); // if file or folder does not exist
            wpPot({
                package: 'AI Content Generate',
                bugReport: '',
                src: '**/*.php',
                domain: 'ai-content-generate',
                destFile: `languages/ai-content-generate.pot`
            });
        });
    } else {
        // --> Create source map
        mix.webpackConfig({output: {devtoolModuleFilenameTemplate: '[resource-path]'}})
            .sourceMaps(false, 'inline-source-map');
        // mix.browserSync({
        // proxy: 'http://wptest.local/',
        //     files: ["src/scss/**/*.scss", "src/**/*.js", 'app/**/*.php']
        // });
    }

    mix.sass( 'src/scss/style.scss', 'assets/css/style.css' )
        .sass(`src/scss/settings-admin.scss`, `assets/css/settings-admin.css`)
        .options( {
            terser: {
                extractComments: false
            },
            processCssUrls: false
        } )
        .webpackConfig( {
            plugins: [
                new WebpackRTLPlugin( {
                    filename: [ /(\.min.css)/i, '.rtl$1' ],
                    minify: isProduction,
                } )
            ],
        } )
}

// mix.js(`src/js/settings-admin.js`, `assets/js/settings-admin.js`).react();


if (process.env.npm_config_package) {
    mix.then(function () {
        const copyTo = path.resolve(`${temDirectory}/${package_slug}`);
        // Select All file then paste on list
        let includes = [
            'app',
            'assets',
            'languages',
            'vendor',
            'index.html',
            'README.txt',
            `${package_slug}.php`
        ];
        fsExtra.ensureDir(copyTo, function (err) {
            if (err) return console.error(err);
            includes.map((include) => {
                fsExtra.copy(
                    `${package_path}/${include}`,
                    `${copyTo}/${include}`,
                    function (err) {
                        if (err) return console.error(err);
                        console.log(
                            cliColor.white(`=> ${emojic.smiley}  ${include} copied...`)
                        );
                    }
                );
            });
            console.log(
                cliColor.white(`=> ${emojic.whiteCheckMark}  Build directory created`)
            );
        });
    });

    return;
}

if (process.env.npm_config_zip) {
    async function getVersion() {
        let data;
        try {
            data = await fsExtra.readFile(package_path + `/${package_slug}.php`, 'utf-8');
        } catch (err) {
            console.error(err);
        }
        const lines = data.split(/\r?\n/);
        let version = '';
        for (let i = 0; i < lines.length; i++) {
            if (lines[i].includes('* Version:') || lines[i].includes('*Version:')) {
                version = lines[i]
                    .replace('* Version:', '')
                    .replace('*Version:', '')
                    .trim();
                break;
            }
        }
        return version;
    }

    const version_get = getVersion();
    version_get.then(function (version) {
        const destinationPath = `${temDirectory}/${package_slug}.${version}.zip`;
        const output = fsExtra.createWriteStream(destinationPath);
        const archive = archiver('zip', {zlib: {level: 9}});
        output.on('close', function () {
            console.log(archive.pointer() + ' total bytes');
            console.log(
                'Archive has been finalized and the output file descriptor has closed.'
            );
            fsExtra.removeSync(`${temDirectory}/${package_slug}`);
        });
        output.on('end', function () {
            console.log('Data has been drained');
        });
        archive.on('error', function (err) {
            throw err;
        });

        archive.pipe(output);
        archive.directory(`${temDirectory}/${package_slug}`, package_slug);
        archive.finalize();
    });
}