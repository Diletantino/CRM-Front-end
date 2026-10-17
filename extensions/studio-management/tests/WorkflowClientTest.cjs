const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '../../..');
function load(relative, Base, Espo = {}) {
    let Result;
    vm.runInNewContext(fs.readFileSync(path.join(root, relative), 'utf8'), {
        define: (_dependencies, factory) => { Result = factory(Base); }, Espo,
    });
    return Result;
}
const prefix = 'client/custom/modules/studio-management/src/views/';
let checks = 0;

(async () => {
    class EditPage {
        optionsToPass = ['returnUrl'];
        setup() {
            assert(this.optionsToPass.includes('params'));
        }
    }
    const Page = load(prefix + 'sm-shift/edit.js', EditPage);
    new Page().setup();
    checks++;

    const calls = [];
    class Record {
        setup() {}
        listenTo() {}
        once() {}
        setFieldReadOnly() {}
        fetch() { return {}; }
        translate(value) { return value; }
        setIsNotChanged() {}
        confirm() { return Promise.resolve(); }
        getUser() { return {isAdmin: () => true}; }
        getRouter() { return {navigate: url => calls.push(['navigate', url])}; }
    }
    const Edit = load(prefix + 'sm-shift/record/edit.js', Record, {
        Ajax: {postRequest: async (url, data) => { calls.push([url, data]); return {}; }},
        Ui: {success() {}},
    });
    const create = (status, params) => {
        const view = new Edit();
        view.options = {params};
        view.buttonList = [{name: 'save'}];
        view.model = {
            id: 'test-shift', isNew: () => false,
            get: name => name === 'status' ? status : [], setMultiple() {}, set() {},
        };
        view.setup();
        return view;
    };
    const start = create('Draft', {start: true});
    assert.equal(start.buttonList[0].label, 'Start Shift');
    checks++;
    start.save = () => Promise.reject('notModified');
    await start.actionSave();
    assert(calls.some(([url]) => url === 'SmShift/test-shift/open'));
    assert(calls.some(([url, target]) => url === 'navigate' && target === '#SmShift/view/test-shift'));
    checks += 2;

    calls.length = 0;
    const invalid = create('Draft', {start: true});
    invalid.save = () => Promise.reject('invalid');
    await assert.rejects(invalid.actionSave(), error => error === 'invalid');
    assert.equal(calls.length, 0);
    checks++;

    const count = create('Counting', {count: true});
    assert.equal(count.buttonList[0].label, 'Complete Calculation');
    await count.actionSave();
    assert(calls.some(([url]) => url === 'SmShift/test-shift/close'));
    checks += 2;

    class Field {
        data() { return {url: '#User/view/model', nameValue: 'Model'}; }
    }
    const Participant = load(prefix + 'sm-shift/fields/participant.js', Field);
    const field = new Participant();
    field.getUser = () => ({isAdmin: () => false, get: () => 'Operator'});
    assert.equal(field.data().profileLinkAllowed, false);
    field.getUser = () => ({isAdmin: () => false, get: () => 'ProducerAdmin'});
    assert.equal(field.data().profileLinkAllowed, true);
    checks += 2;

    const Footer = load(prefix + 'sm-shift/fields/workflow-footer.js', Field);
    const footer = new Footer();
    let saved = 0;
    const record = {actionSave: async () => { saved++; }};
    footer.getParentView = () => ({getParentView: () => record});
    const button = {disabled: false};
    await footer.runWorkflow({preventDefault() {}, stopPropagation() {}, currentTarget: button}, 'actionSave');
    assert.equal(saved, 1);
    assert.equal(button.disabled, false);
    checks += 2;

    console.log(`Workflow client: ${checks} checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
