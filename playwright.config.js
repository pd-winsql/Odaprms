const path = require('path');
const os = require('os');

module.exports = {
  outputDir: path.join(os.tmpdir(), 'ventura-playwright-results'),
  reporter: 'line',
  workers: 1,
};
