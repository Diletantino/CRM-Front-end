const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
let checks = 0;
const eq = (actual, expected) => { assert.equal(actual, expected); checks++; };
const compose = read('compose.yaml');
eq(compose.includes('source: ./client/res/templates/site/footer.tpl'), true);
eq(compose.includes('target: /var/www/html/client/res/templates/site/footer.tpl'), true);
eq(compose.includes('source: ./html/main.html'), true);
eq(compose.includes('target: /var/www/html/html/main.html'), true);
eq(compose.includes('create_host_path: false'), true);
eq(compose.includes('ESPOCRM_CONFIG_USE_CACHE: ${CRM_USE_CACHE:-true}'), true);
const dockerfile = read('docker/runtime/Dockerfile');
for (const target of ['/usr/src/espocrm', '/var/www/html']) {
    eq(dockerfile.includes('client/res/templates/site/footer.tpl ' + target + '/client/res/templates/site/footer.tpl'), true);
    eq(dockerfile.includes('html/main.html ' + target + '/html/main.html'), true);
}
eq(read('.dockerignore').includes('!client/res/templates/site/footer.tpl'), true);
eq(read('.dockerignore').includes('!html/main.html'), true);
eq(dockerfile.includes('studio-template-files.conf /etc/apache2/conf-enabled/studio-template-files.conf'), true);
const apacheTemplates = read('docker/runtime/studio-template-files.conf');
eq(apacheTemplates.includes('EnableMMAP Off'), true);
eq(apacheTemplates.includes('EnableSendfile Off'), true);
eq(read('docker/runtime/apache2-studio-bootstrap').includes('config:set themeParams \'{"navbar":"side"}\' --type=json'), true);
console.log('Footer deployment: ' + checks + ' checks passed.');
