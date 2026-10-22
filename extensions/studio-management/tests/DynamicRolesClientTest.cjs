const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const folder = 'client/custom/modules/studio-management/src/';
function load(file, dependencies = {}) {
    let value;
    vm.runInNewContext(fs.readFileSync(path.join(root, folder, file), 'utf8'), {
        define: (names, factory) => { value = factory(...names.map(name => dependencies[name])); },
    });
    return value;
}
const policy = load('role-policy.js');
let checks = 0;
for (const type of ['Model', 'Operator', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter', '']) {
    const values = {operatorId: 'other', producerId: 'other', status: 'Draft'};
    const permissions = {};
    const view = {
        model: {get: name => values[name]},
        getUser: () => ({id: 'viewer', isAdmin: () => false, get: () => type}),
        getAcl: () => ({getPermissionLevel: name => permissions[name] ?? 'not-set', checkScope: () => true}),
    };
    assert.equal(policy.canOperate(view), false);
    assert.equal(policy.canPrepare(view), false);
    assert.equal(policy.canRevise(view), false);
    checks += 3;
    for (const permission of ['smShiftOperate', 'smShiftPrepare', 'smShiftRevise', 'smStaffAccess']) permissions[permission] = 'all';
    assert.equal(policy.canOperate(view), true);
    assert.equal(policy.canPrepare(view), true);
    assert.equal(policy.canRevise(view), true);
    assert.equal(policy.canViewStaff(view, 'SmModels'), true);
    checks += 4;
    for (const permission of Object.keys(permissions)) permissions[permission] = 'no';
    values.operatorId = values.producerId = 'viewer';
    assert.equal(policy.canOperate(view), false);
    assert.equal(policy.canPrepare(view), false);
    assert.equal(policy.canRevise(view), false);
    assert.equal(policy.canViewStaff(view, 'SmModels'), false);
    checks += 4;
    permissions.smShiftOperate = permissions.smShiftPrepare = 'own';
    permissions.smShiftRevise = 'team';
    assert.equal(policy.canOperate(view), true);
    assert.equal(policy.canPrepare(view), true);
    assert.equal(policy.canRevise(view), true);
    checks += 3;
    class Base { data() { return {}; } }
    const Actions = load('views/sm-shift/fields/workflow-actions.js',
        {'views/fields/base': Base, 'studio-management:role-policy': policy});
    const actions = new Actions();
    Object.assign(actions, view, {getAcl: () => ({checkModel: () => true,
        getPermissionLevel: name => permissions[name] ?? 'not-set'})});
    for (const [status, field] of [['Draft', 'showStart'], ['Open', 'showFinish'], ['Counting', 'showCalculation'], ['Closed', 'showEdit']]) {
        values.status = status;
        assert.equal(actions.data()[field], true);
        permissions.smShiftOperate = permissions.smShiftRevise = 'no';
        assert.equal(actions.data()[field], false);
        permissions.smShiftOperate = 'own';
        permissions.smShiftRevise = 'team';
        checks += 2;
    }
}
class Link {
    setup() {}
    getAutocompleteUrl() { return 'Role?maxSize=20&select=id,name'; }
    actionSelect() { return 'paged-modal'; }
}
const TargetRole = load('views/sm-registration-link/fields/target-role.js', {'views/fields/link': Link});
(async () => {
    const field = new TargetRole();
    field.setup();
    assert.equal(await field.getAutocompleteUrl('test'), 'StudioManagement/registrationRoles?maxSize=20&select=id,name');
    assert.equal(field.actionSelect(), 'paged-modal');
    assert.equal(field.createDisabled, true);
    checks += 3;

    class RoleTableBase {}
    const UnrestrictedTable = load('views/role/record/table.js', {'views/role/record/table': RoleTableBase});
    const table = new UnrestrictedTable();
    let unrestrictedData;
    table.model = {set: value => { unrestrictedData = value; }};
    table.tableDataList = [
        {name: 'User', type: 'record', list: [
            {action: 'create', levelList: ['yes', 'no']},
            {action: 'read', levelList: ['all', 'team', 'own', 'no']},
            {action: 'delete', levelList: null},
        ]},
        {name: 'SmModels', type: 'boolean'},
    ];
    table.removeAllRestrictions();
    assert.deepEqual(JSON.parse(JSON.stringify(unrestrictedData)), {
        data: {User: {create: 'yes', read: 'all'}, SmModels: true},
        fieldData: {},
    });
    checks += 3;

    class RoleEditBase {
        constructor() { this.buttonList = []; }
        addButton(item) { this.buttonList.push(item); }
        setupButtons() { this.buttonWasReadyDuringBaseSetup = this.buttonList.some(item => item.name === 'removeAllRestrictions'); }
        setup() { this.setupButtons(); }
    }
    const UnrestrictedEdit = load('views/role/record/edit.js', {'views/role/record/edit': RoleEditBase});
    const edit = new UnrestrictedEdit();
    edit.setup();
    assert.equal(edit.buttonWasReadyDuringBaseSetup, true);
    checks++;

    console.log('Dynamic roles client: ' + checks + ' checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
