#!/usr/bin/env node
// Site wrapper: the pre-flight itself ships with the aim_demo_library recipe.
// Run from the site root: node demo/preflight.js
process.env.AIM_SITE_ROOT = process.env.AIM_SITE_ROOT || require('path').resolve(__dirname, '..');
process.env.PLAYWRIGHT_PATH = process.env.PLAYWRIGHT_PATH || '/home/niklp/websites/linux-screencast-skills/node_modules/playwright';
require('../web/modules/custom/aim/recipes/aim_demo_library/preflight/preflight.js');
