// Read-only smoke test against the actual Docker PHP renderer, not just HTTP 200.
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const root = process.env.CRM_TEST_URL || 'http://127.0.0.1:8094/';
async function main() {
    const response = await fetch(root);
    assert.equal(response.status, 200);
    const html = await response.text();
    assert.equal(/\{\{[^{}]+\}\}/.test(html), false, 'Unresolved server template placeholder');
    let executableScripts = 0;
    let initialized = false;
    let onPageShow;
    for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
        const [, attributes, source] = match;
        if (/\bsrc\s*=/i.test(attributes) || !source.trim()) continue;
        if (/type\s*=\s*["']application\/json["']/i.test(attributes)) {
            JSON.parse(source);
            continue;
        }
        const script = new vm.Script(source, {filename: root + ' inline bootstrap'});
        script.runInNewContext({
            window: {addEventListener: (event, handler) => {
                assert.equal(event, 'pageshow');
                onPageShow = handler;
            }},
            require: (name, callback) => {
                assert.equal(name, 'studio-management:app');
                callback(class App {
                    constructor(options, ready) {
                        assert.equal(options.id, 'espocrm');
                        assert.equal(typeof options.useCache, 'boolean');
                        ready(this);
                    }
                    start() { initialized = true; }
                });
            },
        });
        executableScripts++;
    }
    assert.ok(executableScripts > 0, 'No inline bootstrap script');
    assert.equal(typeof onPageShow, 'function');
    onPageShow({persisted: false});
    assert.equal(initialized, true, 'Application bootstrap never started');
    initialized = false;
    onPageShow({persisted: true});
    assert.equal(initialized, true, 'Back-forward cache did not resume app');
    const footerUrl = new URL('client/res/templates/site/footer.tpl', root);
    footerUrl.searchParams.set('verify', Date.now());
    const footerResponse = await fetch(footerUrl);
    assert.equal(footerResponse.status, 200);
    const footerBytes = Buffer.from(await footerResponse.arrayBuffer());
    const localFooter = fs.readFileSync(path.resolve(__dirname, '../../../client/res/templates/site/footer.tpl'));
    assert.equal(footerBytes.includes(0), false, 'Apache served NUL bytes in footer template');
    assert.deepEqual(footerBytes, localFooter, 'Served footer differs from the local bind-mounted file');
    console.log('Main HTML runtime: rendered bootstrap syntax and startup passed.');
}
main().catch(error => { console.error(error.message); process.exitCode = 1; });
