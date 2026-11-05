// Load the default @wordpress/scripts config object
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');
const webpack = require('webpack');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
	// Spread the default WordPress Webpack config and extend it
	...defaultConfig,

	  entry: ["./src/admin-assets/src/index.js", './src/blocks_src/blocks.js'],
	//   Please note that the entry point is an array, allowing you to include multiple entry files.

	// Customize the output
	output: {
		...defaultConfig.output,
		filename: 'main.js', // Output the JS file
		path: path.resolve(__dirname, 'build'),
		publicPath: '/', // Set for dev server
	},

	// Add or extend module rules
	module: {
		...defaultConfig.module,
		rules: [
			{
				test: /\.css$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'postcss-loader',
				],
			},
			{
				test: /\.scss$/,
				use: [
					'style-loader',
					'css-loader',
					'sass-loader',
				],
			},
			{
				test: /\.svg$/,
				use: ['@svgr/webpack'],
			},
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: ['@babel/preset-env', '@babel/preset-react']
					}
				},
			},
		],
	},

	// Path alias configuration for cleaner imports
	resolve: {
		...defaultConfig.resolve,
		extensions: ['.js', '.jsx', '.json'],
		alias: {
			...defaultConfig.resolve.alias,
            '@Controls': path.resolve(__dirname, 'wpsp-config/controls'),
            '@Components': path.resolve(__dirname, 'src/blocks_src/components/'),
		},
	},

	// Add plugins like MiniCssExtractPlugin for extracting CSS into separate files
	plugins: [
		...defaultConfig.plugins,
		new MiniCssExtractPlugin({
			filename: '[name].css', // Output CSS file
		})
	],

	// Add devtool for easier debugging in development mode
	devtool:
		process.env.NODE_ENV === 'production' ? 'source-map' : 'eval-source-map',

	// Add optimization for production mode
	optimization: {
		minimize: process.env.NODE_ENV === 'production', // Minimize JS in production mode
	},
};
