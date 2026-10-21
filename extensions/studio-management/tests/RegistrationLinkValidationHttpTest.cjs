// Local Docker regression: node extensions/studio-management/tests/RegistrationLinkValidationHttpTest.cjs
// --ui-fixture keeps only test roles for browser checks; --cleanup removes those exact fixtures.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');

const root = path.resolve(__dirname, '../../..');
const env = Object.fromEntries(fs.readFileSync(path.join(root, '.env'), 'utf8').split(/\r?\n/)
    .filter(line => line && !line.startsWith('#')).map(line => {
        const i = line.indexOf('=');
        return [line.slice(0, i), line.slice(i + 1)];
    }));
const site = new URL(env.CRM_SITE_URL);
assert(['localhost', '127.0.0.1'].includes(site.hostname), 'Run only against a disposable local CRM.');
const auth = Buffer.from(env.CRM_ADMIN_USERNAME + ':' + env.CRM_ADMIN_PASSWORD).toString('base64');
const fixturePath = path.join(root, 'data/studio-invite-ui-fixture.json');
const created = [];
const roles = {};
let checks = 0;
async function request(route, method = 'GET', body) {
    if (route === 'SmRegistrationLink' && method === 'POST') {
        body = {expiresAt: new Date(Date.now() + 86400000).toISOString().slice(0, 19).replace('T', ' '), ...body};
    }
    const response = await fetch(site.origin + '/api/v1/' + route, {
        method, headers: {Authorization: 'Basic ' + auth, 'Espo-Authorization': auth,
            'Content-Type': 'application/json'}, body: body ? JSON.stringify(body) : undefined,
    });
    const text = await response.text();
    let data;
    try { data = JSON.parse(text); } catch { data = {}; }
    return {status: response.status, data};
}
async function create(type, body) {
    const result = await request(type, 'POST', body);
    assert.equal(result.status, 200, 'Create ' + type);
    created.push([type, result.data.id]);
    return result.data;
}
async function cleanup(fixtures) {
    for (const [type, id] of [...fixtures].reverse()) {
        const response = await request(type + '/' + id, 'DELETE');
        assert([200, 204, 404].includes(response.status), 'Cleanup ' + type);
    }
}
(async () => {
    if (process.argv.includes('--cleanup')) {
        const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
        assert.equal(fixture.site, site.origin);
        await cleanup(fixture.created);
        fs.unlinkSync(fixturePath);
        console.log('Registration-link browser fixtures removed (soft delete).');
        return;
    }
    assert(!fs.existsSync(fixturePath), 'Clean up previous browser fixtures first.');
    const suffix = crypto.randomBytes(5).toString('hex');
    let keep = false;
    try {
        const types = ['Model', 'Operator', 'ProducerAdmin', 'TechnicalDirector', 'Recruiter'];
        for (const type of types) {
            roles[type] = await create('Role', {name: 'Invite UI ' + type + ' ' + suffix, smAccountType: type});
        }
        roles.Untyped = await create('Role', {name: 'Invite UI Untyped ' + suffix});
        const availableRoles = await request('StudioManagement/registrationRoles?' + new URLSearchParams({
            maxSize: '100', select: 'id,name,smAccountType', q: suffix,
        }));
        assert.equal(availableRoles.status, 200);
        assert(Object.values(roles).every(role => availableRoles.data.list.some(item => item.id === role.id)));
        checks++;
        for (const [i, type] of types.entries()) {
            // Account type and ACL role are intentionally independent. Include an
            // untyped legacy role and roles carrying a different studio type.
            const role = i === 0 ? roles.Untyped : roles[types[(i + 1) % types.length]];
            const link = await create('SmRegistrationLink', {accountType: type, targetRoleId: role.id});
            assert.match(link.token, /^[A-Za-z0-9_-]{32}$/);
            assert.equal(link.targetRoleId, role.id);
            checks++;
        }
        const modelRoleLink = await create('SmRegistrationLink', {
            accountType: 'Model', targetRoleId: roles.Model.id,
        });
        assert.equal(modelRoleLink.targetRoleId, roles.Model.id);
        checks++;
        const expired = await request('SmRegistrationLink', 'POST', {
            accountType: 'Model', targetRoleId: roles.Model.id, expiresAt: '2020-01-01 00:00:00',
        });
        assert.equal(expired.status, 400);
        assert(expired.data.message?.includes('в будущем'), 'Readable validation in JSON body');
        checks++;
        const me = await request('App/user');
        assert.equal(me.status, 200);
        const invalidRecruiter = await request('SmRegistrationLink', 'POST', {
            accountType: 'Model', targetRoleId: roles.Model.id, recruiterId: me.data.user.id,
        });
        assert.equal(invalidRecruiter.status, 400);
        assert(invalidRecruiter.data.message?.includes('Рекрутер'));
        checks++;
        if (process.argv.includes('--ui-fixture')) {
            await cleanup(created.filter(([type]) => type !== 'Role'));
            fs.writeFileSync(fixturePath, JSON.stringify({site: site.origin,
                created: created.filter(([type]) => type === 'Role'), roles}, null, 2));
            keep = true;
        }
        console.log('Registration-link HTTP: ' + checks + ' checks passed.');
    } finally {
        if (!keep) { await cleanup(created); }
    }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
