const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
let checks = 0;
const equal = (a, b) => { assert.equal(a, b); checks++; };
const metadataPath = 'custom/Espo/Modules/StudioManagement/Resources/metadata/app/acl.json';
const acl = JSON.parse(fs.readFileSync(path.join(root, metadataPath), 'utf8'));
equal(acl.adminMandatory.scopeLevel.Role.read, 'all');
equal(acl.adminMandatory.scopeLevel.Role.create, 'yes');
equal(acl.mandatory.scopeLevel.Role, 'SmRoleAdministration');
const selectPath = 'custom/Espo/Modules/StudioManagement/Resources/metadata/selectDefs/Role.json';
equal(JSON.parse(fs.readFileSync(path.join(root, selectPath), 'utf8')).accessControlFilterResolverClassName,
    'Espo\\Core\\Select\\AccessControl\\FilterResolvers\\Boolean');
equal(fs.readFileSync(path.join(root, selectPath), 'utf8'),
    fs.readFileSync(path.join(root, 'extensions/studio-management/files', selectPath), 'utf8'));
equal(fs.readFileSync(path.join(root, metadataPath), 'utf8'),
    fs.readFileSync(path.join(root, 'extensions/studio-management/files', metadataPath), 'utf8'));
let Controller;
vm.runInNewContext(fs.readFileSync(path.join(root,
    'client/custom/modules/studio-management/src/controllers/role.js'), 'utf8'),
    {define: (_names, factory) => { Controller = factory(class {}); }});
const controller = new Controller();
let isAdmin = true, grant = false, checksOfDelegatedScope = 0;
controller.getUser = () => ({isAdmin: () => isAdmin});
controller.getAcl = () => ({checkScope: scope => {
    equal(scope, 'SmRoleAdministration'); checksOfDelegatedScope++; return grant;
}});
for (const action of ['list', 'create', 'view', 'edit']) equal(controller.checkAccess(action), true);
equal(checksOfDelegatedScope, 0);
isAdmin = false;
equal(controller.checkAccess('list'), false);
grant = true;
for (const action of ['list', 'create', 'view', 'edit']) equal(controller.checkAccess(action), true);
const entrypoint = fs.readFileSync(path.join(root, 'docker/runtime/studio-entrypoint.sh'), 'utf8');
let TeamController;
const teamPath = 'client/custom/modules/studio-management/src/controllers/team.js';
vm.runInNewContext(fs.readFileSync(path.join(root, teamPath), 'utf8'),
    {define: (_names, factory) => { TeamController = factory(class {}); }});
const team = new TeamController();
const teamLevels = {read: true, create: true, edit: true, delete: true};
team.getUser = () => ({isAdmin: () => false});
team.getAcl = () => ({checkScope: (scope, action) => { equal(scope, 'Team'); return teamLevels[action]; }});
for (const action of Object.keys(teamLevels)) {
    equal(team.checkAccess(action), true);
    teamLevels[action] = false;
    equal(team.checkAccess(action), false);
}
team.getUser = () => ({isAdmin: () => true});
equal(team.checkAccess('create'), true);
equal(fs.readFileSync(path.join(root, teamPath), 'utf8'),
    fs.readFileSync(path.join(root, 'extensions/studio-management/files', teamPath), 'utf8'));
equal(JSON.parse(fs.readFileSync(path.join(root,
    'custom/Espo/Modules/StudioManagement/Resources/metadata/clientDefs/Team.json'), 'utf8')).controller,
    'studio-management:controllers/team');
const bootstrap = fs.readFileSync(path.join(root, 'docker/runtime/apache2-studio-bootstrap'), 'utf8');
equal(entrypoint.includes('exec docker-entrypoint.sh apache2-studio-bootstrap "$@"'), true);
equal(entrypoint.includes('config:get isInstalled'), false);
equal(bootstrap.includes('config:set tabList'), true);
equal(bootstrap.includes('"Role"'), true);
equal(bootstrap.includes('--type=json'), true);
equal(bootstrap.trimEnd().endsWith('exec "$@"'), true);
equal(fs.readFileSync(path.join(root, 'docker/runtime/Dockerfile'), 'utf8')
    .includes('COPY --chmod=755 docker/runtime/apache2-studio-bootstrap'), true);
console.log('Handoff access client: ' + checks + ' checks passed.');
