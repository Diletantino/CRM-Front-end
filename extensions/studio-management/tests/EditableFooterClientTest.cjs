const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const prefix = 'client/custom/modules/studio-management/src/';
let checks = 0;
const eq = (a, b) => { assert.equal(a, b); checks++; };
function load(file, dependency, Base) {
    let Result;
    const source = fs.readFileSync(path.join(root, prefix, file), 'utf8');
    eq(source, fs.readFileSync(path.join(root, 'extensions/studio-management/files', prefix, file), 'utf8'));
    vm.runInNewContext(source, {define: (names, factory) => {
        eq(names[0], dependency);
        Result = factory(Base);
    }});
    return Result;
}
class BaseMaster {
    afterRender() {
        this.standardLifecycleCalls = (this.standardLifecycleCalls || 0) + 1;
        const footer = this.getView('footer');
        if (footer) footer.$el.html('<p>Native fallback</p>');
    }
}
const Master = load('views/site/master.js', 'views/site/master', BaseMaster);
for (const initial of ['', '<p>onlineCRM — Support</p>', '<p><a href="">ㅤ</a></p>', '<p>© EspoCRM, Inc.</p>']) {
    let html = initial;
    const footer = {$el: {html(value) { if (arguments.length) html = value; return html; }}};
    const view = new Master();
    view.getView = name => { eq(name, 'footer'); return footer; };
    view.afterRender();
    eq(html, initial);
    eq(view.standardLifecycleCalls, 1);
    view.afterRender();
    eq(html, initial);
    eq(view.standardLifecycleCalls, 2);
}
const withoutFooter = new Master();
withoutFooter.getView = () => null;
withoutFooter.afterRender();
eq(withoutFooter.standardLifecycleCalls, 1);
const App = load('app.js', 'app', class {});
eq(new App().masterView, 'studio-management:views/site/master');
const template = fs.readFileSync(path.join(root, 'html/main.html'), 'utf8');
eq(template.includes("appClass === 'app' ? 'studio-management:app' : appClass"), true);
console.log('Editable footer: ' + checks + ' checks passed.');
