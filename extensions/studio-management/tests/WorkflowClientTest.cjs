const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '../../..');
function load(relative, Base, Espo = {}, context = {}) {
    let Result;
    vm.runInNewContext(fs.readFileSync(path.join(root, relative), 'utf8'), {
        define: (_dependencies, factory) => { Result = factory(Base); }, Espo, ...context,
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
    const notifications = [];
    let postError;
    class Record {
        setup() {}
        listenTo() {}
        once() {}
        setFieldReadOnly() {}
        fetch() { return {}; }
        translate(value) { return value; }
        setIsNotChanged() {}
        enableActionItems() { this.actionsEnabled = true; }
        confirm() { return Promise.resolve(); }
        getUser() { return {isAdmin: () => true}; }
        getRouter() { return {navigate: url => calls.push(['navigate', url])}; }
    }
    const Edit = load(prefix + 'sm-shift/record/edit.js', Record, {
        Ajax: {postRequest: async (url, data) => {
            calls.push([url, data]);
            if (postError) {
                throw postError;
            }
            return {};
        }},
        Ui: {success() {}, notify: value => notifications.push(value)},
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
    assert.equal(invalid.actionsEnabled, true);
    assert.equal(notifications.at(-1), false);
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

    // Both entry points must recover after validation/save failures and failed /open requests.
    for (const failure of ['invalid', {status: 400}, {status: 500}]) {
        const failed = create('Draft', {start: true});
        failed.save = async () => {
            failed.actionsEnabled = false; // Espo disables the toolbar before save.
            if (failure === 'invalid') {
                throw failure;
            }
        };
        postError = failure === 'invalid' ? undefined : failure;
        calls.length = 0;
        notifications.length = 0;
        await assert.rejects(failed.actionSave(), error => error === failure);
        assert.equal(failed.actionsEnabled, true);
        assert.equal(notifications.at(-1), false);
        assert(!calls.some(([url]) => url === 'navigate'));
        assert.equal(calls.filter(([url]) => url.endsWith('/open')).length, failure === 'invalid' ? 0 : 1);
        checks++;

        footer.getParentView = () => failed;
        await footer.runWorkflow({preventDefault() {}, stopPropagation() {}, currentTarget: button}, 'actionSave');
        assert.equal(button.disabled, false);
        assert.equal(failed.actionsEnabled, true);
        checks++;

        // A corrected form can start without refreshing the page.
        postError = undefined;
        failed.save = () => Promise.reject('notModified');
        calls.length = 0;
        await failed.actionSave();
        assert(calls.some(([url]) => url === 'SmShift/test-shift/open'));
        assert(calls.some(([url, target]) => url === 'navigate' && target === '#SmShift/view/test-shift'));
        checks++;
    }

    class View {}
    const stored = new Map();
    const cleanUrls = [];
    const window = {
        location: {
            hash: '#access=' + 'a'.repeat(64),
            search: '?entryPoint=studioRegister',
            pathname: '/',
        },
        sessionStorage: {
            setItem: (key, value) => stored.set(key, value),
            getItem: key => stored.get(key) || null,
        },
        history: {
            replaceState: (_state, _title, url) => cleanUrls.push(url),
        },
    };
    const Registration = load(prefix + 'registration.js', View, {}, {
        window,
        document: {title: 'CRM'},
        URLSearchParams,
        TextEncoder,
    });
    const registration = new Registration();
    assert.equal(registration.consumeTokenFromLocation(), 'a'.repeat(64));
    assert.equal(stored.get('studioAccessToken'), 'a'.repeat(64));
    assert.equal(cleanUrls.at(-1), '/?entryPoint=studioRegister');
    checks += 3;

    let response = {operatorId: 'operator', modelId: 'model', producerId: 'producer',
        siteData: [{site: 'CB', login: 'model', password: 'fixture'}]};
    const TeamEdit = load(prefix + 'sm-shift/record/edit.js', Record, {
        Ajax: {getRequest: async () => response}, Ui: {error() {}},
    });
    const teamView = new TeamEdit();
    const values = {teamId: 'group', status: 'Draft', modelId: 'old-model'};
    teamView.model = {get: name => values[name], set: data => Object.assign(values, data)};
    await teamView.loadTeamDefaults();
    assert.equal(values.operatorId, 'operator');
    assert.equal(values.producerId, 'producer');
    assert.equal(values.siteData[0].login, 'model');
    checks++;
    values.teamId = null;
    await teamView.loadTeamDefaults();
    assert.equal(values.modelId, null);
    assert.equal(values.producerId, null);
    assert.equal(values.siteData.length, 0);
    checks++;
    values.teamId = 'group-a';
    let resolveDefaults;
    response = new Promise(resolve => { resolveDefaults = resolve; });
    const pending = teamView.loadTeamDefaults();
    values.teamId = 'group-b';
    resolveDefaults({modelId: 'stale-model'});
    await pending;
    assert.notEqual(values.modelId, 'stale-model');
    checks++;

    console.log(`Workflow client: ${checks} checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
