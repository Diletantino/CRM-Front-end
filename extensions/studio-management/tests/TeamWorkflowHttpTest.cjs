// Run against a disposable LOCAL install: node .../TeamWorkflowHttpTest.cjs [--keep]
// Uses its .env admin credentials. Never run against production with real staff.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../../..');
const env = Object.fromEntries(fs.readFileSync(path.join(root, '.env'), 'utf8')
    .split(/\r?\n/).filter(l => l && !l.startsWith('#')).map(l => {
        const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1)];
    }));
const base = env.CRM_SITE_URL + '/api/v1/';
if (!['localhost', '127.0.0.1'].includes(new URL(base).hostname)) {
    throw new Error('This test is restricted to a local disposable CRM.');
}
const auth = Buffer.from(env.CRM_ADMIN_USERNAME + ':' + env.CRM_ADMIN_PASSWORD).toString('base64');
const headers = {'Content-Type': 'application/json', Authorization: 'Basic ' + auth,
    'Espo-Authorization': auth, 'Espo-Authorization-By-Token': 'false'};
let checks = 0;
const fixtures = [];
const registry = path.join(root, 'data/tmp/team-workflow-fixtures.json');
fs.mkdirSync(path.dirname(registry), {recursive: true});
const expect = (value, message) => { assert(value, message); checks++; };
async function request(url, method = 'GET', body, actorHeaders = headers) {
    const res = await fetch(base + url, {method, headers: actorHeaders, body: body === undefined ? undefined : JSON.stringify(body)});
    const text = await res.text();
    let data; try { data = JSON.parse(text); } catch { data = text; }
    return {status: res.status, data, message: data.message || res.headers.get('x-status-reason') || text};
}
async function create(type, body) {
    const r = await request(type, 'POST', body);
    assert.equal(r.status, 200, 'Create ' + type + ': ' + r.message);
    fixtures.push([type, r.data.id]);
    return r.data;
}
async function ok(url, method, body) {
    const r = await request(url, method, body);
    assert.equal(r.status, 200, url + ': ' + r.message);
    return r.data;
}
async function cleanup(list) {
    for (const [type, id] of [...list].reverse()) {
        if (['SmShiftFinancialSnapshot', 'SmShiftExpense', 'SmShift'].includes(type)) {
            continue; // Immutable finances are cleaned by the local CLI fixture cleanup.
        }
        const r = await request(type + '/' + id, 'DELETE');
        assert([200, 204, 404].includes(r.status), 'Cleanup failed: ' + type + ' ' + r.message);
    }
}
(async () => {
    if (process.argv.includes('--cleanup')) {
        await cleanup(JSON.parse(fs.readFileSync(registry, 'utf8')).fixtures);
        fs.unlinkSync(registry);
        console.log('Team workflow fixtures soft-deleted.');
        return;
    }
    let passed = false;
    try {
        const suffix = crypto.randomBytes(4).toString('hex');
        const user = (type, extra = {}) => create('User', {userName: 'team-test-' + type.toLowerCase() + '-' + suffix,
            firstName: 'Тест', lastName: type + suffix, type: 'regular', isActive: true, smAccountType: type, ...extra});
        const producer = await user('ProducerAdmin', {smProducerSharePercentOverride: '10'});
        const producer2 = await user('ProducerAdmin', {userName: 'team-test-prod2-' + suffix});
        const operator = await user('Operator', {smOperatorSharePercentOverride: '10'});
        const model = await user('Model', {smSharePercentOverride: '20', smBirthDate: '2000-01-01',
            smSiteData: [{site: 'CB', login: 'fixture-model', password: 'fixture-not-real'}]});
        const model2 = await user('Model', {userName: 'team-test-model2-' + suffix});
        const team = await create('Team', {name: 'Тест — состав группы ' + suffix,
            smProducerId: producer.id, smOperatorId: operator.id, smModelId: model.id});
        const members = await ok('Team/' + team.id + '/users');
        expect([producer.id, operator.id, model.id].every(id => members.list.some(u => u.id === id)),
            'Explicit slots must create native group membership.');
        const defaults = await ok('StudioManagement/teams/' + team.id + '/shiftDefaults');
        expect(defaults.producerId === producer.id && defaults.operatorId === operator.id &&
            defaults.modelId === model.id && defaults.siteData[0].login === 'fixture-model',
            'Group defaults return all participants and profile credentials.');
        const draft = await create('SmShift', {teamId: team.id, currency: 'USD'});
        expect(draft.producerId === producer.id && draft.operatorId === operator.id && draft.modelId === model.id,
            'Creating a shift with ONLY the group must resolve all three slots on the backend.');
        expect(draft.siteData[0].login === 'fixture-model' && draft.modelBirthDate === '2000-01-01',
            'Group-only creation loads model profile defaults.');
        const open = await ok('SmShift/' + draft.id + '/open', 'POST');
        expect(open.status === 'Open', 'Draft -> Open');
        await ok('Team/' + team.id, 'PUT', {smProducerId: producer2.id});
        const changedMembers = await ok('Team/' + team.id + '/users');
        expect(changedMembers.list.some(u => u.id === producer2.id) &&
            !changedMembers.list.some(u => u.id === producer.id), 'Changing a slot updates native membership.');
        const counting = await ok('SmShift/' + draft.id + '/finish', 'POST');
        expect(counting.status === 'Counting', 'Open -> Counting');
        const sites = open.siteData.map(row => ({...row, earning: '100.00', offlineBonus: '10.00'}));
        const closed = await ok('SmShift/' + draft.id + '/close', 'POST', {siteData: sites, screenshotsIds: []});
        expect(closed.status === 'Closed' && Number(closed.totalGross) === 110, 'Counting -> Closed with correct total.');
        expect(closed.producerId === producer.id && Number(closed.producerShare) === 11,
            'Closing must use the original producer even after group changes.');
        const snapshot = await ok('SmShiftFinancialSnapshot?where[0][type]=equals&where[0][attribute]=shiftId&where[0][value]=' + draft.id);
        snapshot.list.forEach(row => fixtures.push(['SmShiftFinancialSnapshot', row.id]));
        expect(snapshot.list.length === 1 && snapshot.list[0].producerId === producer.id,
            'Exactly one financial snapshot with original producer.');
        expect(closed.expenseBreakdown.some(row => row.category === 'Producer' && row.userId === producer.id && Number(row.amount) === 11),
            'Producer expense stored correctly.');
        const duplicateClose = await request('SmShift/' + draft.id + '/close', 'POST', {siteData: sites, screenshotsIds: []});
        expect(duplicateClose.status === 409, 'Duplicate close must not duplicate finances.');
        const revised = await ok('SmShift/' + draft.id + '/revise', 'POST',
            {siteData: sites.map(row => ({...row, earning: '200.00'})), screenshotsIds: []});
        expect(revised.producerId === producer.id && Number(revised.producerShare) === 21,
            'Revision must also preserve original producer.');

        const native = await create('Team', {name: 'Тест — добавление участников ' + suffix});
        for (const member of [producer, operator, model]) {
            await ok('Team/' + native.id + '/users', 'POST', {id: member.id});
        }
        const filled = await ok('Team/' + native.id);
        expect(filled.smProducerId === producer.id && filled.smOperatorId === operator.id && filled.smModelId === model.id,
            'Adding users via native group panel must populate the studio slots.');
        const duplicate = await request('Team/' + native.id + '/users', 'POST', {id: model2.id});
        expect(duplicate.status === 400 && duplicate.message.includes('уже выбран'),
            'A second model must be rejected with a Russian explanation: ' + JSON.stringify(duplicate));
        const invalid = await request('Team/' + native.id, 'PUT', {smProducerId: model.id});
        expect(invalid.status === 400 && invalid.message.includes('продюсер'),
            'Wrong account type cannot occupy producer slot.');
        const duplicateFromUser = await request('User/' + model2.id, 'PUT',
            {teamsIds: [native.id]});
        expect(duplicateFromUser.status === 400 && duplicateFromUser.message.includes('уже выбран'),
            'Editing user groups cannot add a second model.');
        const newDuplicate = await request('User', 'POST', {userName: 'team-test-duplicate-' + suffix,
            firstName: 'Test', type: 'regular', isActive: true, smAccountType: 'Model', teamsIds: [native.id]});
        expect(newDuplicate.status === 400, 'Creating a user in a populated slot cannot add a second model.');
        await ok('Team/' + native.id + '/users', 'DELETE', {id: operator.id});
        const removed = await ok('Team/' + native.id);
        expect(!removed.smOperatorId, 'Removing a member clears their slot.');
        const incomplete = await request('SmShift', 'POST', {teamId: native.id, currency: 'USD'});
        expect(incomplete.status === 400 && incomplete.message.includes('оператор'),
            'Incomplete group is rejected before shift creation, not when closing.');
        await ok('User/' + operator.id + '/teams', 'POST', {id: native.id});
        const inverse = await ok('Team/' + native.id);
        expect(inverse.smOperatorId === operator.id, 'Adding from user side must fill group slot.');
        const same = await create('Team', {name: 'Тест — продюсер-оператор ' + suffix,
            smProducerId: producer.id, smOperatorId: producer.id, smModelId: model.id});
        const sameShift = await create('SmShift', {teamId: same.id, currency: 'USD'});
        expect(sameShift.operatorId === producer.id && sameShift.producerId === producer.id,
            'Producer can also occupy operator slot.');
        const role = await create('Role', {name: 'Прод ' + suffix, smAccountType: 'ProducerAdmin'});
        const legacy = await user('', {userName: 'team-test-legacy-' + suffix, rolesIds: [role.id]});
        expect(legacy.smAccountType === 'ProducerAdmin', 'Role metadata resolves studio type for manual user creation.');
        const legacyTeam = await create('Team', {name: 'Тест — тип по роли ' + suffix,
            smProducerId: legacy.id, smOperatorId: operator.id, smModelId: model.id});
        const legacyShift = await create('SmShift', {teamId: legacyTeam.id, currency: 'USD'});
        await ok('SmShift/' + legacyShift.id + '/open', 'POST');
        await ok('SmShift/' + legacyShift.id + '/finish', 'POST');
        const legacyClosed = await ok('SmShift/' + legacyShift.id + '/close', 'POST', {siteData: sites, screenshotsIds: []});
        expect(legacyClosed.status === 'Closed', 'Manually created producer with role can close a real HTTP shift.');
        await ok('User/' + legacy.id, 'PUT', {isActive: false});
        const inactive = await request('StudioManagement/teams/' + legacyTeam.id + '/shiftDefaults');
        expect(inactive.status === 400 && inactive.message.includes('продюсер'), 'Inactive producer blocked before use.');

        const operatorRole = await create('Role', {name: 'Тест оператор ' + suffix, smAccountType: 'Operator',
            data: {SmShift: {read: 'own', edit: 'own', create: 'yes', delete: 'no'},
                Team: {read: 'team'}, User: {read: 'team', edit: 'no'}}});
        const invite = await create('SmRegistrationLink', {accountType: 'Operator', targetRoleId: operatorRole.id,
            expiresAt: new Date(Date.now() + 86400000).toISOString().slice(0, 19).replace('T', ' ')});
        expect(/^[A-Za-z0-9_-]{32}$/.test(invite.token), 'New personal token contains exactly 32 URL-safe random characters.');
        expect(invite.registrationUrl.endsWith('/?entryPoint=studioRegister#' + invite.token),
            'Copied personal link uses the configured domain and a fragment secret.');
        const email = 'team-test-login-' + suffix + '@example.invalid';
        const password = 'TestOnly-' + crypto.randomBytes(12).toString('hex') + 'Aa7';
        const registration = await ok('StudioManagement/register', 'POST', {token: invite.token, email,
            fullName: 'Тест оператор со входом', password});
        fixtures.push(['User', registration.userId]);
        const credentials = Buffer.from(email + ':' + password).toString('base64');
        const login = await request('App/user', 'GET', undefined, {...headers, Authorization: 'Basic ' + credentials,
            'Espo-Authorization': credentials, 'X-Studio-Access-Token': invite.token});
        expect(login.status === 200 && login.data.user?.id === registration.userId, 'Operator personal-link login works.');
        const session = Buffer.from(email + ':' + login.data.token).toString('base64');
        const actor = {...headers, Authorization: 'Basic ' + session, 'Espo-Authorization': session,
            'Espo-Authorization-By-Token': 'true'};
        const forbiddenInvite = await request('SmRegistrationLink', 'POST',
            {accountType: 'Operator', targetRoleId: operatorRole.id}, actor);
        expect(forbiddenInvite.status === 403, 'A non-administrator cannot create registration links.');
        const forbiddenInviteList = await request('SmRegistrationLink', 'GET', undefined, actor);
        expect(forbiddenInviteList.status === 403, 'A non-administrator cannot list or open registration links.');
        const actorTeam = await create('Team', {name: 'Тест — смена обычного оператора ' + suffix,
            smProducerId: producer.id, smOperatorId: registration.userId, smModelId: model.id});
        const actorDefaults = await request('StudioManagement/teams/' + actorTeam.id + '/shiftDefaults', 'GET', undefined, actor);
        expect(actorDefaults.status === 200 && actorDefaults.data.modelId === model.id, 'Own operator can load group defaults.');
        const actorCreate = await request('SmShift', 'POST', {teamId: actorTeam.id, currency: 'USD'}, actor);
        expect(actorCreate.status === 200 && actorCreate.data.operatorId === registration.userId,
            'Ordinary operator creates a shift using only their group: ' + actorCreate.message);
        fixtures.push(['SmShift', actorCreate.data.id]);
        for (const action of ['open', 'finish']) {
            const r = await request('SmShift/' + actorCreate.data.id + '/' + action, 'POST', {}, actor);
            expect(r.status === 200, 'Ordinary operator can ' + action + ': ' + r.message);
        }
        const actorClosed = await request('SmShift/' + actorCreate.data.id + '/close', 'POST',
            {siteData: sites, screenshotsIds: []}, actor);
        expect(actorClosed.status === 200 && actorClosed.data.status === 'Closed', 'Ordinary operator closes own shift: ' + actorClosed.message);
        const actorRevise = await request('SmShift/' + actorCreate.data.id + '/revise', 'POST',
            {siteData: sites, screenshotsIds: []}, actor);
        expect(actorRevise.status === 403, 'Operator cannot revise a closed shift.');
        const outsiderDefaults = await request('StudioManagement/teams/' + same.id + '/shiftDefaults', 'GET', undefined, actor);
        expect(outsiderDefaults.status === 403, 'Unrelated operator cannot read another group credentials.');
        const outsiderCreate = await request('SmShift', 'POST', {teamId: same.id, currency: 'USD'}, actor);
        expect(outsiderCreate.status === 403, 'Group-only shift create cannot bypass team access restrictions.');
        passed = true;
        if (process.argv.includes('--keep')) {
            fs.writeFileSync(registry, JSON.stringify({fixtures, teamId: native.id, producer, operator, model,
                sameTeamId: same.id, closedShiftId: draft.id}, null, 2));
            console.log('UI fixtures retained: ' + JSON.stringify({teamId: native.id, sameTeamId: same.id,
                producer: producer.name, operator: operator.name, model: model.name}));
        }
        console.log('Live team workflow: ' + checks + ' checks passed.');
    } catch (error) {
        console.error('Original workflow failure:', error);
        throw error;
    } finally {
        if (!passed || !process.argv.includes('--keep')) {
            fs.writeFileSync(registry, JSON.stringify({fixtures}, null, 2));
        }
        if (!passed || !process.argv.includes('--keep')) {
            await cleanup(fixtures);
            console.log('Temporary account/group fixtures soft-deleted; use the local CLI cleanup for immutable financial fixtures.');
        }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
