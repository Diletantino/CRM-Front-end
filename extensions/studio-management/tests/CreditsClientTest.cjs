const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const client = 'client/custom/modules/studio-management/';
let checks = 0;
const eq = (actual, expected) => { assert.equal(actual, expected); checks++; };
function load(file, Base, globals = {}) {
    let result;
    vm.runInNewContext(fs.readFileSync(path.join(root, client, file), 'utf8'), {
        define: (_names, factory) => { result = factory(Base); }, ...globals,
    });
    return result;
}
const classes = new Set();
const properties = new Map();
const originalNotice = '© 2026 EspoCRM, Inc.';
const footer = {textContent: originalNotice,
    classList: {add: name => classes.add(name), remove: name => classes.delete(name),
        toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name)},
    style: {setProperty: (key, value) => properties.set(key, value), removeProperty: key => properties.delete(key)}};
let rect = {left: 0, top: 0, right: 224, width: 224, height: 900, bottom: 900};
let mode = 'side';
const sidebar = {getBoundingClientRect: () => rect,
    querySelector: () => ({getBoundingClientRect: () => ({top: 874, height: 26})})};
const window = {innerWidth: 1200, innerHeight: 900,
    addEventListener: name => eq(name, 'resize'), removeEventListener: name => eq(name, 'resize')};
let disconnected = 0;
class Observer { observe() {} disconnect() { disconnected++; } }
const document = {body: {getAttribute: () => mode}, querySelector: selector => {
    if (selector === 'body > footer') return footer;
    eq(selector, '#navbar .navbar-left-container'); return sidebar;
}};
const Item = load('src/views/site/credits-item.js', class {}, {
    window, document, ResizeObserver: Observer, MutationObserver: Observer,
});
const item = new Item();
item.afterRender();
eq(classes.has('studio-credit-in-sidebar'), true);
eq(properties.get('--studio-credit-left'), '0px');
eq(properties.get('--studio-credit-top'), '810px');
eq(properties.get('--studio-credit-width'), '224px');
eq(classes.has('studio-credit-compact'), false);
eq(footer.textContent, originalNotice);
rect.width = rect.right = 49;
item.updateCreditPosition();
eq(classes.has('studio-credit-compact'), true);
eq(properties.get('--studio-credit-width'), '49px');
window.innerWidth = 500;
item.updateCreditPosition();
eq(classes.has('studio-credit-in-sidebar'), false);
eq(properties.size, 0);
eq(footer.textContent, originalNotice);
window.innerWidth = 1200;
rect.left = -1;
item.updateCreditPosition();
eq(classes.has('studio-credit-in-sidebar'), false);
rect.left = 0;
item.updateCreditPosition();
eq(classes.has('studio-credit-in-sidebar'), true);
mode = 'top';
item.updateCreditPosition();
eq(classes.has('studio-credit-in-sidebar'), false);
mode = 'side';
item.updateCreditPosition();
item.onRemove();
eq(disconnected, 2);
eq(classes.has('studio-credit-in-sidebar'), false);
eq(classes.has('studio-credit-compact'), false);
eq(properties.size, 0);
eq(footer.textContent, originalNotice);
class Controller { getUser() { return {id: 'regular-user'}; } handleCheckAccess() {} main(name) { this.mainView = name; } }
const Credits = load('src/controllers/sm-credits.js', Controller);
const controller = new Credits();
eq(controller.checkAccess(), true);
controller.actionIndex();
eq(controller.mainView, 'studio-management:views/sm-credits/index');
controller.getUser = () => ({});
eq(controller.checkAccess(), false);
const Page = load('src/views/sm-credits/index.js', class {setPageTitle(value) { this.title = value; }});
const page = new Page(); page.updatePageTitle(); eq(page.title, 'Credits');
const sourceLicense = fs.readFileSync(path.join(root, 'LICENSE.txt'));
eq(fs.readFileSync(path.join(root, client, 'legal/AGPL-3.0.txt')).equals(sourceLicense), true);
const template = fs.readFileSync(path.join(root, client, 'res/templates/sm-credits/index.tpl'), 'utf8');
const navbarTemplate = fs.readFileSync(path.join(root, client, 'res/templates/site/credits-item.tpl'), 'utf8');
eq(navbarTemplate.includes('href="#SmCredits"'), false);
eq(JSON.parse(fs.readFileSync(path.join(root,
    'custom/Espo/Modules/StudioManagement/Resources/metadata/scopes/SmCredits.json'), 'utf8')).tab, true);
eq(template.includes("translate 'Attribution'"), true);
eq(template.includes('EspoCRM, Inc.'), true);
for (const removed of ['License', 'Warranty', 'Source code']) {
    eq(template.includes("translate '" + removed + "'"), false);
}
eq(fs.readFileSync(path.join(root, 'extensions/studio-management/files', client,
    'res/templates/sm-credits/index.tpl'), 'utf8'), template);
for (const lang of ['ru_RU', 'en_US']) {
    const labels = JSON.parse(fs.readFileSync(path.join(root,
        'custom/Espo/Modules/StudioManagement/Resources/i18n/' + lang + '/SmCredits.json'), 'utf8')).labels;
    for (const [, label] of template.matchAll(/translate '([^']+)'/g)) {
        eq(typeof labels[label], 'string');
    }
}
console.log('Credits client: ' + checks + ' checks passed.');
