/// !------------
/// THIS IS A TEMPLATE - FEEL FREE TO CHANGE
/// !------------

// learn the Webpack setup from here https://symfony.com/doc/current/frontend.html

'use strict';

// dependencies
const path = require('path');
const Encore = require('@symfony/webpack-encore');
const Autoprefixer = require('autoprefixer');

// source directories
const PLUGINS_DIR = path.join(path.resolve(), 'web', 'wp-content', 'plugins');
const THEMES_DIR = path.join(path.resolve(), 'web', 'wp-content', 'themes');

// My plugin
const MY_PLUGIN_DIR = path.join(PLUGINS_DIR, 'my-plugin');
const MY_PLUGIN_ASSETS_DIR = path.join(MY_PLUGIN_DIR, 'assets-src');
const MY_PLUGIN_JS_SRC = path.join(MY_PLUGIN_ASSETS_DIR, 'js');
const MY_PLUGIN_DIST_DIR = path.join(MY_PLUGIN_DIR, 'public');
const MY_PLUGIN_DIST_REL_WEB_PATH = '/wp-content/plugins/my-plugin/public';

// Setup of the Webpack using Encore for the Microstrategy-REST plugin
Encore
	// initialization
	.setOutputPath(MY_PLUGIN_DIST_DIR)
	.setPublicPath(MY_PLUGIN_DIST_REL_WEB_PATH)
	.setManifestKeyPrefix('')
	.configureFilenames({
		css: 'css/[name].css',
		js: 'js/[name].js'
	})

	.addEntry('smm', path.join(MY_PLUGIN_JS_SRC, 'pages', 'smm', 'smm.js'))

	// Number of paths for being copied during builds.
	.copyFiles([
		{
			from: path.join(MY_PLUGIN_ASSETS_DIR, 'images'),
			to: path.join('images', '[path][name].[ext]')
		},
		{
			from: path.join(MY_PLUGIN_JS_SRC, 'common'),
			to: path.join('js', '[name].[ext]')
		}
	])

	// Setup
	.configureCssLoader(function (config) {
		// todo: remove once the switch and work with webpack will be set for every developer
		config.url = false;
	})
	.configureUrlLoader({
		fonts: {limit: 4096},
		images: {limit: 4096}
	})
	.enableSassLoader(function (config) {
		config.outputStyle = Encore.isProduction() ? 'compressed' : 'expanded';
	})
	.enablePostCssLoader(function (options) {
		options.plugins = [
			new Autoprefixer()
		];
	})
	.configureBabel(function (options) {
	}, {
		exclude: [
			path.join(MY_PLUGIN_ASSETS_DIR, 'js', 'vendor'),
			// /\bcore-js\b/,
			// '@babel/runtime-corejs3'
		],
		// useBuiltIns: 'usage',
		// corejs: 3
	})
	.autoProvideVariables({
		jQuery: 'jquery',
		$: 'jquery',
		_: 'underscore',
	})
	.addExternals([
		{
			underscore: '_',
			jquery: 'jQuery',
		},
	])
	.enableVersioning()

	// dev settings
	.enableSingleRuntimeChunk()
	.cleanupOutputBeforeBuild()
	.enableSourceMaps(!Encore.isProduction())
	.enableBuildNotifications(true)
;

const myPluginConfig = Encore.getWebpackConfig();
myPluginConfig.name = 'myPluginConfig';
Encore.reset();

module.exports = [myPluginConfig];
