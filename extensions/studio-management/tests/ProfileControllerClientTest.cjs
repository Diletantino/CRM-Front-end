const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../../../client/custom/modules/studio-management/src');
let Policy, Controller, checks = 0;
vm.runInNewContext(fs.readFileSync(path.join(root, 'role-policy.js'), 'utf8'), {
    define: (_deps, factory) => { Policy = factory(); },
});
class NativeUserController {
    actionView() { this.result = 'nativeView'; }
    actionEdit() { this.result = 'nativeEdit'; }
    main(name, options) { this.result = name; this.options = options; }
    getRouter() { throw Error('Forbidden profiles must use the native error screen.'); }
}
class AccessDenied extends Error {}
vm.runInNewContext(fs.readFileSync(path.join(root, 'controllers/user.js'), 'utf8'), {
    define: (_deps, factory) => { Controller = factory(NativeUserController, Policy, {AccessDenied}); },
});
for (const level of ['no', 'team', 'all']) {
    const controller = new Controller();
    controller.getUser = () => ({isAdmin: () => false});
    controller.getAcl = () => ({getPermissionLevel: () => level});
    if (level === 'no') {
        assert.throws(() => controller.actionView({id: 'test-user'}), AccessDenied); checks++;
    } else {
        controller.actionView({id: 'test-user'});
        assert.equal(controller.result, level === 'team'
            ? 'studio-management:views/user/simple-profile' : 'nativeView'); checks++;
    }
    if (level === 'team') { assert.equal(controller.options.profileId, 'test-user'); checks++; }
    if (level === 'all') {
        controller.actionEdit({id: 'test-user'});
        assert.equal(controller.result, 'nativeEdit'); checks++;
    } else {
        assert.throws(() => controller.actionEdit({id: 'test-user'}), AccessDenied); checks++;
    }
}
console.log(`Profile controller client: ${checks} checks passed.`);
