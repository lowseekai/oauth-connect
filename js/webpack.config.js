const config = require('flarum-webpack-config');

module.exports = config({
  entries: {
    admin: './admin.js',
  },
});
