const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const prefix = 'client/custom/modules/studio-management/src/';
let checks = 0;
const eq = (a, b) => { assert.equal(a, b); checks++; };
function load(file, dependency, Base, globals = {}) {
    let Result;
    const source = fs.readFileSync(path.join(root, prefix, file), 'utf8');
    eq(source, fs.readFileSync(path.join(root, 'extensions/studio-management/files', prefix, file), 'utf8'));
    vm.runInNewContext(source, {define: (names, factory) => {
        eq(names[0], dependency);
        Result = factory(Base);
    }, ...globals});
    return Result;
}
class BaseMaster {
    setup() { this.standardSetupCalled = true; }
    afterRender() {
        this.standardLifecycleCalls = (this.standardLifecycleCalls || 0) + 1;
        const footer = this.getView('footer');
        if (footer) footer.$el.html('<p>Native fallback</p>');
    }
}
const Master = load('views/site/master.js', 'views/site/master', BaseMaster);
const configured = new Master();
configured.views = {header: {view: 'header'}, main: {view: false}, footer: {fullSelector: 'body > footer'}};
configured.setup();
eq(configured.standardSetupCalled, true);
eq(configured.views.header.view, 'header');
eq(configured.views.main.view, false);
eq(configured.views.footer.fullSelector, 'body > footer');
eq(configured.views.footer.view, 'studio-management:views/site/footer');
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
async function verifyFreshFooter() {
    let source = '<p>Local footer</p>';
    let status = 200;
    let requestFailed = false;
    let errors = 0;
    const Footer = load('views/site/footer.js', 'view', class {
        getBasePath() { return '/crm/'; }
        wait(promise) { this.ready = promise; }
        setTemplateContent(value) { this.compiledContent = value; }
    }, {
        fetch: async (url, options) => {
            eq(url, '/crm/client/res/templates/site/footer.tpl');
            eq(options.cache, 'no-store');
            eq(options.credentials, 'same-origin');
            if (requestFailed) throw new Error('Offline');
            return {ok: status === 200, status, text: async () => source};
        },
        console: {error: () => errors++},
    });
    for (const content of ['<p>Local footer</p>', '<p>Edited without rebuilding</p>', '']) {
        source = content;
        const footer = new Footer();
        footer.setup();
        await footer.ready;
        eq(footer.templateContent, content);
        eq(footer.compiledContent, content);
    }
    source = 'invalid\0template';
    const damaged = new Footer(); damaged.setup(); await damaged.ready;
    eq(damaged.templateContent, '');
    eq(damaged.compiledContent, '');
    status = 404;
    const missing = new Footer(); missing.setup(); await missing.ready;
    eq(missing.templateContent, '');
    eq(missing.compiledContent, '');
    requestFailed = true;
    const offline = new Footer(); offline.setup(); await offline.ready;
    eq(offline.templateContent, '');
    eq(offline.compiledContent, '');
    eq(errors, 3);
    console.log('Editable footer: ' + checks + ' checks passed.');
}
verifyFreshFooter().catch(error => { console.error(error); process.exitCode = 1; });
