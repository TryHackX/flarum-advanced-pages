const config = require('flarum-webpack-config')();

// Force CJS resolution for packages with ESM exports fields
config.resolve = config.resolve || {};
config.resolve.conditionNames = ['require', 'node', 'default'];

// Exclude highlight.js from Babel processing.
// Babel's transform-runtime injects ESM imports into CJS files,
// which webpack rejects. highlight.js ships pre-built CJS code that
// doesn't need Babel transformation.
config.module.rules.forEach(rule => {
  if (rule.loader && rule.loader.includes('babel-loader')) {
    rule.exclude = /node_modules[\\/]highlight\.js/;
  }
});

module.exports = config;
