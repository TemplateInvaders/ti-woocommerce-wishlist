/**
 * WPGulp Configuration File
 *
 * 1. Edit the variables as per your project requirements.
 * 2. In paths you can add <<glob or array of globs>>.
 *
 * @package WPGulp
 */

const config = require( './package.json' );

module.exports = {

	Styles: [
		{
			styleSRC: './sass/admin.scss', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		},
		{
			styleSRC: './assets/css/admin-form.css', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		},
		{
			styleSRC: './assets/css/admin-setup.css', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		},
		{
			styleSRC: './sass/public.scss', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		},
		{
			styleSRC: './sass/theme.scss', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		},
		{
			styleSRC: './sass/webfont.scss', // Path to .scss file.
			styleDestination: './assets/css/' // Path to place the compiled CSS file.
		}
	],

	// JS Public options.
	jsPublicSRC: './assets/js/public/*.js', // Path to JS custom scripts folder.
	jsPublicDestination: './assets/js/', // Path to place the compiled JS custom scripts file.
	jsPublicFile: 'public', // Compiled JS custom file name. Default set to custom i.e. custom.js.

	// JS Admin options.
	jsAdminSRC: './assets/js/admin/*.js', // Path to JS custom scripts folder.
	jsAdminDestination: './assets/js/', // Path to place the compiled JS custom scripts file.
	jsAdminFile: 'admin', // Compiled JS custom file name. Default set to custom i.e. custom.js.

	// JS Dev options.
	jsDevSRC: './assets/js/dev/*.js', // Path to JS custom scripts folder.
	jsDevDestination: './assets/js/', // Path to place the compiled JS custom scripts file.
	jsDevFile: 'dev', // Compiled JS custom file name. Default set to custom i.e. custom.js.

	// Images options.
	imgSRC: './assets/img/raw/**/*', // Source folder of images which should be optimized and watched. You can also specify types e.g. raw/**.{png,jpg,gif} in the glob.
	imgDST: './assets/img/', // Destination folder of optimized images. Must be different from the imagesSRC folder.

	// Watch files paths.
	watchStyles: './sass/**/*.scss', // Path to all *.scss files inside css folder and inside them.
	watchJsPublic: './assets/js/public/*.js', // Path to all public JS files.
	watchJsAdmin: './assets/js/admin/*.js', // Path to all custom JS files.
	watchPhp: './**/*.php', // Path to all PHP files.

	// Translation options.
	textDomain: config.name, // Your textdomain here.
	translationFile: config.name + '.pot', // Name of the translation file.
	translationDestination: './languages', // Where to save the translation files.
	packageName: config.title, // Package name.
	bugReport: config.author_url + 'help/', // Where can users report bugs.
	lastTranslator: config.author, // Last translator Email ID.
	team: config.author, // Team's Email ID.

	// Browsers you care about for autoprefixing. Browserlist https://github.com/ai/browserslist
	// The following list is set as per WordPress requirements. Though, Feel free to change.
	BROWSERS_LIST: [
		'last 2 version',
		'> 1%',
		'ie >= 11',
		'last 1 Android versions',
		'last 1 ChromeAndroid versions',
		'last 2 Chrome versions',
		'last 2 Firefox versions',
		'last 2 Safari versions',
		'last 2 iOS versions',
		'last 2 Edge versions',
		'last 2 Opera versions'
	]
};
