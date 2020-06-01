# End to End Testing Environment

A reusable and extendable E2E testing environment for WooCommerce extensions.

## Installation

```bash
npm install @woocommerce/e2e-environment --save
npm install jest --global
```

## Configuration

The `@woocommerce/e2e-environment` package exports configuration objects that can be consumed in JavaScript config files in your project. Additionally, it includes a hosting container for running tests and includes instructions for creating your Travis CI setup.

### Babel Config

Make sure you `npm install @babel/preset-env --save` if you have not already done so. Afterwards, extend your project's `babel.config.js` to contain the expected presets for E2E testing.

```js
const { babelConfig: e2eBabelConfig } = require( '@woocommerce/e2e-environment' );

module.exports = function( api ) {
	api.cache( true );

	return {
		...e2eBabelConfig,
		presets: [
			...e2eBabelConfig.presets,
			'@wordpress/babel-preset-default',
		],
		....
	};
};
```

### ES Lint Config

The E2E environment uses Puppeteer for headless browser testing, which uses certain globals variables. Avoid ES Lint errors by extending the config.

```js
const { esLintConfig: baseConfig } = require( '@woocommerce/e2e-environment' );

module.exports = {
	...baseConfig,
	root: true,
	parser: 'babel-eslint',
	extends: [
		...baseConfig.extends,
		'wpcalypso/react',
		'plugin:jsx-a11y/recommended',
	],
	plugins: [
		...baseConfig.plugins,
		'jsx-a11y',
	],
	env: {
		...baseConfig.env,
		browser: true,
		node: true,
	},
	globals: {
		...baseConfig.globals,
		wp: true,
		wpApiSettings: true,
		wcSettings: true,
	},
	....
};
```

### Jest Config

The E2E environment uses Jest as a test runner. Extending the base config is needed in order for Jest to run your project's test files.

```js
const path = require( 'path' );
const { jestConfig: baseE2Econfig } = require( '@woocommerce/e2e-environment' );

module.exports = {
	...baseE2Econfig,
	// Specify the path of your project's E2E tests here.
	roots: [ path.resolve( __dirname, '../specs' ) ],
};
```

**NOTE:** Your project's Jest config file is expected to be found at: `tests/e2e/config/jest.config.js`.

### Webpack Config

The E2E environment provides a `@woocommerce/e2e-utils` alias for easy use of the WooCommerce E2E test helpers.

```js
const { webpackAlias: coreE2EAlias } = require( '@woocommerce/e2e-environment' );

module.exports = {
	....
	resolve: {
		alias: {
			...coreE2EAlias,
			....
		},
	},
};
```

### Container Setup

Depending on the project and testing scenario, the built in testing environment container might not be the best solution for testing. This could be local testing where there is already a testing container, a repository that isn't a plugin or theme and there are multiple folders mapped into the container, or similar. The `e2e-environment` container supports using either the built in container or an external container. See the the appropriate readme for  details:

- [Built In Container](./builtin.md)
- [External Container](./external.md)

## Additional information

Refer to [`tests/e2e/specs`](https://github.com/woocommerce/woocommerce/tree/master/tests/e2e/specs) for some test examples, and [`tests/e2e`](https://github.com/woocommerce/woocommerce/tree/master/tests/e2e) for general information on e2e tests.
