/**
 * Gulpfile.
 *
 * Gulp with WordPress.
 *
 * Implements:
 *      1. Live reloads browser with BrowserSync.
 *      2. CSS: Sass to CSS conversion, error catching, Autoprefixing, Sourcemaps,
 *         CSS minification, and Merge Media Queries.
 *      3. JS: Concatenates & uglifies Vendor and Custom JS files.
 *      4. Images: Minifies PNG, JPEG, GIF and SVG images.
 *      5. Watches files for changes in CSS or JS.
 *      6. Watches files for changes in PHP.
 *      7. Corrects the line endings.
 *      8. InjectCSS instead of browser page reload.
 *      9. Generates .pot file for i18n and l10n.
 *
 * @tutorial https://github.com/ahmadawais/WPGulp
 * @author Ahmad Awais <https://twitter.com/MrAhmadAwais/>
 */

/**
 * Load WPGulp Configuration.
 *
 */
const config = require( './wpgulp.config.js' );

/**
 * Load Plugins.
 *
 * Load gulp plugins and passing them semantic names.
 */
const gulp = require( 'gulp' ); // Gulp of-course.

// CSS related plugins.
var sass = require( 'gulp-sass' )( require( 'sass' ) ); // Gulp plugin for Sass compilation.
const minifycss = require( 'gulp-uglifycss' ); // Minifies CSS files.
const autoprefixer = require( 'gulp-autoprefixer' ); // Autoprefixing magic.
const mmq = require( 'gulp-merge-media-queries' ); // Combine matching media queries into one.
const rtlcss = require( 'gulp-rtlcss' ); // Generates RTL stylesheet.

// JS related plugins.
const concat = require( 'gulp-concat' ); // Concatenates JS files.
const uglify = require( 'gulp-uglify' ); // Minifies JS files.
const babel = require( 'gulp-babel' ); // Compiles ESNext to browser compatible JS.

// Image related plugins.
const imagemin = require( 'gulp-imagemin' ); // Minify PNG, JPEG, GIF and SVG images with imagemin.

// Utility related plugins.
const rename = require( 'gulp-rename' ); // Renames files E.g. style.css -> style.min.css.
const lineec = require( 'gulp-line-ending-corrector' ); // Consistent Line Endings for non UNIX systems. Gulp Plugin for Line Ending Corrector (A utility that makes sure your files have consistent line endings).
const filter = require( 'gulp-filter' ); // Enables you to work on a subset of the original files by filtering them using a glob.
const sourcemaps = require( 'gulp-sourcemaps' ); // Maps code in a compressed file (E.g. style.css) back to it’s original position in a source file (E.g. structure.scss, which was later combined with other css files to generate style.css).
const notify = require( 'gulp-notify' ); // Sends message notification to you.
const browserSync = require( 'browser-sync' ).create(); // Reloads browser and injects CSS. Time-saving synchronized browser testing.
const wpPot = require( 'gulp-wp-pot' ); // For generating the .pot file.
const sort = require( 'gulp-sort' ); // Recommended to prevent unnecessary changes in pot-file.
const cache = require( 'gulp-cache' ); // Cache files in stream for later use.
const remember = require( 'gulp-remember' ); //  Adds all the files it has ever seen back into the stream.
const plumber = require( 'gulp-plumber' ); // Prevent pipe breaking caused by errors from gulp plugins.
const beep = require( 'beepbeep' );
const merge = require( 'merge-stream' );
const defaults = require( 'lodash.defaults' );
const checktextdomain = require( 'gulp-checktextdomain' );
const header = require( 'gulp-header' );
const bump = require( 'gulp-bump' );
const zip = require( 'gulp-vinyl-zip' ).zip; // Gulp plugin to generate zip folder and remove unwanted files.
const readme = require( 'gulp-readme-to-markdown' );

/**
 * Custom Error Handler.
 *
 * @param Mixed err
 */
const errorHandler = r => {
	notify.onError( '\n\n❌  ===> ERROR: <%= error.message %>\n' )( r );
	beep();

	// this.emit('end');
};


/**
 * This function does the following:
 *    1. Gets the source scss file
 *    2. Compiles Sass to CSS
 *    3. Writes Sourcemaps for it
 *    4. Autoprefixes it and generates style.css
 *    5. Renames the CSS file with suffix .min.css
 *    6. Minifies the CSS file and generates style.min.css
 *    7. Injects CSS or reloads the browser via browserSync
 */
function processStyle( gulpStream, processOptions = {}) {
	processOptions = defaults( processOptions, {
		styleDestination: config.styleDestination
	});

	return gulpStream
		.pipe( plumber( errorHandler ) )

		// .pipe(sourcemaps.init())
		.pipe(
			sass({
				errLogToConsole: config.errLogToConsole,
				outputStyle: config.outputStyle,
				precision: config.precision
			})
		)
		.on( 'error', sass.logError )

		// .pipe(sourcemaps.write({includeContent: false}))
		// .pipe(sourcemaps.init({loadMaps: true}))
		.pipe( autoprefixer( config.BROWSERS_LIST ) )

		// .pipe(sourcemaps.write('./'))
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( processOptions.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( rtlcss() )                     // Convert to RTL
		.pipe( rename({suffix: '-rtl'}) )
		.pipe( gulp.dest( processOptions.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
	;
}

function minifyStyle( gulpStream, processOptions = {}) {
	processOptions = defaults( processOptions, {
		styleDestination: config.styleDestination
	});

	return gulpStream
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( sourcemaps.init() )
		.pipe( sourcemaps.write({includeContent: false}) )
		.pipe( sourcemaps.init({loadMaps: true}) )
		.pipe( sourcemaps.write( './' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( processOptions.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( mmq({log: true}) ) // Merge Media Queries only for .min.css version.
		.pipe( rename({suffix: '.min'}) )
		.pipe( minifycss({maxLineLen: 10}) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( header( banner, {pkg: pkg}) )
		.pipe( gulp.dest( processOptions.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
	;
}

/**
 * Task: `Styles`.
 *
 * Compiles Sass, Autoprefixes it and Minifies CSS.
 *
 * This task does the following:
 *    1. Gets the source scss file
 *    2. Compiles Sass to CSS
 *    3. Writes Sourcemaps for it
 *    4. Autoprefixes it and generates CSS file
 *    5. Renames the CSS file with suffix .min.css
 *    6. Minifies the CSS file and generates .min.css
 *    7. Injects CSS or reloads the browser via browserSync
 */
gulp.task( 'Styles', ( done ) => {

	// Exit task when no addon styles
	if ( 0 === config.Styles.length ) {
		return done();
	}

	// Process each addon style
	var tasks = config.Styles.map( function( addon ) {

		return processStyle(
			gulp.src( addon.styleSRC, {allowEmpty: true}),
			{styleDestination: addon.styleDestination}
		).pipe( notify({message: '\n\n✅  ===> STYLES — completed!\n', onLast: true}) );

	});

	return merge( tasks );
});

gulp.task( 'StylesMin', ( done ) => {
	return minifyStyle( gulp.src([ './assets/css/*.css', '!./assets/css/*.min.css' ], {allowEmpty: true}),
		{styleDestination: './assets/css/'}).pipe( notify({
		message: '\n\n✅  ===> STYLES MIN — completed!\n',
		onLast: true
	}) );
});


/**
 * Task: `publicJS`.
 *
 * Concatenate and uglify public JS scripts.
 *
 * This task does the following:
 *     1. Gets the source folder for JS vendor files
 *     2. Concatenates all the files and generates public.js
 *     3. Renames the JS file with suffix .min.js
 *     4. Uglifes/Minifies the JS file and generates public.min.js
 */
gulp.task( 'publicJS', () => {
	return gulp
		.src( config.jsPublicSRC, {since: gulp.lastRun( 'publicJS' )}) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe(
			babel({
				presets: [
					[
						'@babel/preset-env', // Preset to compile your modern JS to ES5.
						{
							targets: {browsers: config.BROWSERS_LIST} // Target browser list to support.
						}
					]
				]
			})
		)
		.pipe( remember( config.jsPublicSRC ) ) // Bring all files back to stream.
		.pipe( concat( config.jsPublicFile + '.js' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsPublicDestination ) )
		.pipe(
			rename({
				basename: config.jsPublicFile,
				suffix: '.min'
			})
		)
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( header( banner, {pkg: pkg}) )
		.pipe( gulp.dest( config.jsPublicDestination ) )
		.pipe( notify({message: '\n\n✅  ===> Public JS — completed!\n', onLast: true}) );
});

/**
 * Task: `adminJS`.
 *
 * Concatenate and uglify custom JS scripts.
 *
 * This task does the following:
 *     1. Gets the source folder for JS custom files
 *     2. Concatenates all the files and generates custom.js
 *     3. Renames the JS file with suffix .min.js
 *     4. Uglifes/Minifies the JS file and generates custom.min.js
 */
gulp.task( 'adminJS', () => {
	return gulp
		.src( config.jsAdminSRC, {since: gulp.lastRun( 'adminJS' )}) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe(
			babel({
				presets: [
					[
						'@babel/preset-env', // Preset to compile your modern JS to ES5.
						{
							targets: {browsers: config.BROWSERS_LIST} // Target browser list to support.
						}
					]
				]
			})
		)
		.pipe( remember( config.jsAdminSRC ) ) // Bring all files back to stream.
		.pipe( concat( config.jsAdminFile + '.js' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsAdminDestination ) )
		.pipe(
			rename({
				basename: config.jsAdminFile,
				suffix: '.min'
			})
		)
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( header( banner, {pkg: pkg}) )
		.pipe( gulp.dest( config.jsAdminDestination ) )
		.pipe( notify({message: '\n\n✅  ===> Admin JS — completed!\n', onLast: true}) );
});

/**
 * Task: `devJS`.
 *
 * Concatenate and uglify custom JS scripts.
 *
 * This task does the following:
 *     1. Gets the source folder for JS custom files
 *     2. Concatenates all the files and generates custom.js
 *     3. Renames the JS file with suffix .min.js
 *     4. Uglifes/Minifies the JS file and generates custom.min.js
 */
gulp.task( 'devJS', () => {
	return gulp
		.src( config.jsDevSRC, {since: gulp.lastRun( 'devJS' )}) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe(
			babel({
				presets: [
					[
						'@babel/preset-env', // Preset to compile your modern JS to ES5.
						{
							targets: {browsers: config.BROWSERS_LIST} // Target browser list to support.
						}
					]
				]
			})
		)
		.pipe( remember( config.jsDevSRC ) ) // Bring all files back to stream.
		.pipe( concat( config.jsDevFile + '.js' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsDevDestination ) )
		.pipe(
			rename({
				basename: config.jsDevFile,
				suffix: '.min'
			})
		)
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		// .pipe( header( banner, {pkg: pkg}) )
		.pipe( gulp.dest( config.jsDevDestination ) )
		.pipe( notify({message: '\n\n✅  ===> Dev JS — completed!\n', onLast: true}) );
});

/**
 * Task: `images`.
 *
 * Minifies PNG, JPEG, GIF and SVG images.
 *
 * This task does the following:
 *     1. Gets the source of images raw folder
 *     2. Minifies PNG, JPEG, GIF and SVG images
 *     3. Generates and saves the optimized images
 *
 * This task will run only once, if you want to run it
 * again, do it with the command `gulp images`.
 *
 * Read the following to change these options.
 * @link https://github.com/sindresorhus/gulp-imagemin
 */
gulp.task( 'images', () => {
	return gulp
		.src( config.imgSRC )
		.pipe(
			cache(
				imagemin([
					imagemin.gifsicle({interlaced: true}),
					imagemin.mozjpeg({progressive: true}),
					imagemin.optipng({optimizationLevel: 3}), // 0-7 low-high.
					imagemin.svgo({
						plugins: [ {removeViewBox: true}, {cleanupIDs: false} ]
					})
				])
			)
		)
		.pipe( gulp.dest( config.imgDST ) )
		.pipe( notify({message: '\n\n✅  ===> IMAGES — completed!\n', onLast: true}) );
});


/**
 * WP POT Translation File Generator.
 *
 * This task does the following:
 * 1. Gets the source of all the PHP files
 * 2. Sort files in stream by path or any custom sort comparator
 * 3. Applies wpPot with the variable set at the top of this file
 * 4. Generate a .pot file of i18n that can be used for l10n to build .mo file
 */
gulp.task( 'translate', () => {
	return gulp
		.src( config.watchPhp )
		.pipe( sort() )
		.pipe(
			wpPot({
				domain: config.textDomain,
				package: config.packageName + ' - ' + pkg.version,
				bugReport: config.bugReport,
				lastTranslator: config.lastTranslator,
				team: config.team
			})
		)
		.pipe( gulp.dest( config.translationDestination + '/' + config.translationFile ) )
		.pipe( notify({message: '\n\n✅  ===> TRANSLATE — completed!\n', onLast: true}) );
});

gulp.task(
	'checktextdomain',
	function() {
		return gulp
			.src( './**/*.php' )
			.pipe(
				checktextdomain(
					{
						text_domain: [ config.textDomain ], //Specify allowed domain(s)
						keywords: [ //List keyword specifications
							'__:1,2d',
							'_e:1,2d',
							'_x:1,2c,3d',
							'esc_html__:1,2d',
							'esc_html_e:1,2d',
							'esc_html_x:1,2c,3d',
							'esc_attr__:1,2d',
							'esc_attr_e:1,2d',
							'esc_attr_x:1,2c,3d',
							'_ex:1,2c,3d',
							'_n:1,2,4d',
							'_nx:1,2,4c,5d',
							'_n_noop:1,2,3d',
							'_nx_noop:1,2,3c,4d'
						]
					}
				)
			)
			.pipe( notify({message: '\n\n✅  ===> Check Text Domain — completed!\n', onLast: true}) );
	}
);

// using data from package.json
var pkg = require( './package.json' );
var banner = [ '/**',
	' * <%= pkg.title %> - <%= pkg.description %>',
	' * @version <%= pkg.version %>',
	' * @link <%= pkg.homepage %>',
	' */',
	'' ].join( '\n' );

gulp.task( 'bump', function() {
	const constant = 'TINVWL_FVERSION';

	gulp.src( './ti-woocommerce-wishlist.php' )
		.pipe( bump({version: pkg.version}) )
		.pipe( bump({
			regex: new RegExp( '([<|\'|"]?(' + constant + ')[>|\'|"]?[ ]*[:=,]?[ ]*[\'|"]?[a-z]?)(\\d+\\.\\d+\\.\\d+)(-[0-9A-Za-z\.-]+)?(\\+[0-9A-Za-z\.-]+)?([\'|"|<]?)', 'i' ),
			version: pkg.version
		}) )
		.pipe( gulp.dest( './' ) );

	return gulp.src( './readme.txt' )
		.pipe( bump({key: 'Stable tag', version: pkg.version}) )
		.pipe( gulp.dest( './' ) )
		.pipe( notify({message: '\n\n✅  ===> Bump — completed!\n', onLast: true}) );
});

/**
 * Generate .zip
 *
 */

gulp.task( 'zip', function() {
	return gulp.src([
		'./**/*',
		'!./{node_modules,node_modules/**/*}',
		'!./.git',
		'!./assets/js/dev/**',
		'!./assets/js/dev.**',
		'!./sass/**',
		'!./assets/img/raw/**',
		'!./assets/js/public/**',
		'!./assets/js/admin/**',
		'!./gulpfile.babel.js',
		'!./wpgulp.config.js',
		'!./readme.md',
		'!./.eslintrc.js',
		'!./.eslintignore',
		'!./.editorconfig',
		'!./composer.json',
		'!./composer.lock',
		'!./package.json',
		'!./package-lock.json' ],
	{base: './../'})
		.pipe( zip( pkg.name + '.zip' ) )
		.pipe( gulp.dest( './../' ) )
		.pipe( notify({message: '\n\n✅  ===> ZIP — completed!\n', onLast: true}) );
});

gulp.task( 'readme', function() {
	return gulp.src([ 'readme.txt' ])
		.pipe( readme({
			details: false,
			screenshot_url: 'https://ps.w.org/{plugin}/assets/{screenshot}.png',
			slug: pkg.name
		}) )
		.pipe( gulp.dest( '.' ) )
		.pipe( notify({message: '\n\n✅  ===> Readme — completed!\n', onLast: true}) );
});

/**
 * Watch Tasks.
 *
 * Watches for file changes and runs specific tasks.
 */
gulp.task(
	'watches',
	gulp.parallel( 'Styles', 'publicJS', 'adminJS', 'images', () => {
		gulp.watch( config.watchStyles, gulp.series( 'Styles' ) );
		gulp.watch( config.watchJsPublic, gulp.series( 'publicJS' ) );
		gulp.watch( config.watchJsAdmin, gulp.series( 'adminJS' ) );
		gulp.watch( config.imgSRC, gulp.series( 'images' ) );
	})
);

gulp.task(
	'release',
	gulp.series( 'Styles', 'StylesMin', 'publicJS', 'adminJS', 'images', 'checktextdomain', 'translate', 'bump', 'readme', 'zip' )
);
