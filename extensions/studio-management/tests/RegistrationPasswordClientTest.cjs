const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const client = 'client/custom/modules/studio-management/';
let View;
let requests = 0, checks = 0;
vm.runInNewContext(fs.readFileSync(path.join(root, client, 'src/views/registration.js'), 'utf8'), {
    define: (_names, factory) => { View = factory(class {}); },
    Espo: {Ajax: {postRequest: async () => { requests++; return {userName: 'fixture@example.invalid'}; }}},
});
const eq = (a, b) => { assert.equal(a, b); checks++; };
const view = new View();
for (const [password, expected] of [
    ['Aa1234567', false], ['aa12345678', false], ['AA12345678', false],
    ['Abcdefghij', false], ['Аа12345678', false], ['Aa12345678', true],
    ['HelloWorld9', true], ['Aa1😀😀😀😀', false], ['Aa1😀😀😀😀😀😀😀', true],
]) eq(view.meetsBasicPasswordRequirements(password), expected);
let values = {fullName: 'Test User', email: 'fixture@example.invalid', password: 'weak', passwordConfirm: 'weak'};
let message = '', disabled = false;
const $message = {removeClass() { return this; }, addClass() { return this; }, text(value) { message = value; return this; }};
view.options = {token: 'test-token'};
view.translate = key => key;
view.reRender = async () => {};
view.$el = {find: selector => {
    if (selector === '.registration-message') return $message;
    if (selector === '[data-action="register"]') return {prop: (_key, value) => { disabled = value; }};
    const name = selector.match(/name="([^"]+)"/)[1];
    return {val: () => values[name]};
}};
(async () => {
    await view.submit();
    eq(message, 'passwordRequirements'); eq(requests, 0); eq(disabled, false);
    values.password = 'Aa12345678'; values.passwordConfirm = 'different';
    await view.submit();
    eq(message, 'passwordsDoNotMatch'); eq(requests, 0);
    values.passwordConfirm = values.password;
    await view.submit();
    eq(requests, 1); eq(view.options.invitation.mode, 'login');
    const template = fs.readFileSync(path.join(root, client, 'res/templates/registration.tpl'), 'utf8');
    eq(template.includes('aria-describedby="registration-password-requirements"'), true);
    eq(template.includes("translate 'passwordRequirements'"), true);
    eq(template.includes('minlength="10"'), true);
    for (const language of ['ru_RU', 'en_US']) {
        const relative = 'custom/Espo/Modules/StudioManagement/Resources/i18n/' + language + '/SmRegistrationLink.json';
        eq(typeof JSON.parse(fs.readFileSync(path.join(root, relative), 'utf8')).messages.passwordRequirements, 'string');
        eq(fs.readFileSync(path.join(root, relative), 'utf8'),
            fs.readFileSync(path.join(root, 'extensions/studio-management/files', relative), 'utf8'));
    }
    for (const relative of [client + 'src/views/registration.js', client + 'res/templates/registration.tpl']) {
        eq(fs.readFileSync(path.join(root, relative), 'utf8'),
            fs.readFileSync(path.join(root, 'extensions/studio-management/files', relative), 'utf8'));
    }
    console.log('Registration password client: ' + checks + ' checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
