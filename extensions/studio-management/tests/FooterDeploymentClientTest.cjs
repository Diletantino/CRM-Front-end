const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
let checks = 0;
const eq = (actual, expected) => { assert.equal(actual, expected); checks++; };
const itemPath = 'client/custom/modules/studio-management/src/views/site/credits-item.js';
eq(read(itemPath), read('extensions/studio-management/files/' + itemPath));
let Item;
const classes = new Set();
const properties = new Map();
const footer = {
    textContent: '© EspoCRM',
    classList: {add: n => classes.add(n), remove: n => classes.delete(n),
        toggle: (n, enabled) => enabled ? classes.add(n) : classes.delete(n)},
    style: {setProperty: (k, v) => properties.set(k, v), removeProperty: k => properties.delete(k)},
};
let mode = 'side';
const rect = {left: 0, right: 224, top: 0, bottom: 900, width: 224, height: 900};
const sidebar = {getBoundingClientRect: () => rect, querySelector: () => null};
const window = {innerWidth: 1200, innerHeight: 900, addEventListener() {}, removeEventListener() {}};
const document = {body: {getAttribute: () => mode}, querySelector: selector =>
    selector === 'body > footer' ? footer : sidebar};
let disconnected = 0;
class Observer { observe() {} disconnect() { disconnected++; } }
vm.runInNewContext(read(itemPath), {
    define: (_names, factory) => { Item = factory(class {}); },
    window, document, ResizeObserver: Observer, MutationObserver: Observer,
});
const item = new Item();
item.afterRender(); // Must not throw: a failure here interrupts master afterRender and sidebar setup.
eq(classes.has('studio-credit-in-sidebar'), true);
eq(properties.get('--studio-credit-width'), '224px');
item.positionCredit();
rect.width = rect.right = 49;
item.positionCredit();
eq(classes.has('studio-credit-compact'), true);
window.innerWidth = 500;
item.positionCredit();
eq(classes.has('studio-credit-in-sidebar'), false);
window.innerWidth = 1200;
mode = 'top';
item.positionCredit();
eq(properties.size, 0);
item.onRemove();
eq(disconnected, 2);
eq(footer.textContent, '© EspoCRM');
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
eq(read('docker/runtime/apache2-studio-bootstrap').includes('config:set themeParams \'{"navbar":"side"}\' --type=json'), true);
console.log('Footer deployment: ' + checks + ' checks passed.');
