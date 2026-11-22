// Run only in the isolated localhost CRM created for this access regression.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const stateFile = process.env.CRM_ACCESS_FIXTURE;
const state = JSON.parse(fs.readFileSync(stateFile, 'utf8'));
const base = new URL(state.url + '/api/v1/');
assert(['localhost','127.0.0.1'].includes(base.hostname));
const basic = (name,password) => {
    const value = Buffer.from(name + ':' + password).toString('base64');
    return {'Espo-Authorization':value,'Espo-Authorization-By-Token':'false','Content-Type':'application/json'};
};
const admin = basic('admin',state.password);
const suffix = require('node:crypto').randomBytes(5).toString('hex');
let checks=0;
const check = (condition,label) => {assert(condition,label); checks++;};
async function request(route,method='GET',body,headers=admin) {
    const response = await fetch(new URL(route,base),{method,headers,body:body === undefined ? undefined : JSON.stringify(body)});
    const text=await response.text();
    let data;try{data=JSON.parse(text);}catch{data=text;}
    return {status:response.status,data};
}
async function ok(route,method,body,headers) {
    const response=await request(route,method,body,headers);
    assert.equal(response.status,200,route+': '+JSON.stringify(response.data));
    return response.data;
}
async function role(name,extra={}) {
    return ok('Role','POST',{name:'Access check '+name,
        data:{User:{read:'all',edit:'all',create:'yes'},Team:{read:'all',edit:'all',create:'yes'},
            SmAnalytics:true,SmModels:true,SmOperators:true,SmRegistrationLink:{create:'yes',read:'all',edit:'all'},
            SmShiftFinancialSnapshot:{read:'all'}},
        smProfileAccessPermission:'no',smPersonalAnalyticsPermission:'no',
        smAccountOnboardingPermission:'no',smInvitationsPermission:'yes',smStaffAccessPermission:'all',
        smAnalyticsAccessPermission:'no',...extra});
}
async function account(name,role,accountType='Operator',recruiterId) {
    const invite=await ok('SmRegistrationLink','POST',{accountType,targetRoleId:role.id,recruiterId,
        expiresAt:new Date(Date.now()+86400000).toISOString().slice(0,19).replace('T',' ')});
    const password='TestOnly-Aa9-'+require('node:crypto').randomBytes(8).toString('hex');
    const email='a'+suffix+require('node:crypto').randomBytes(2).toString('hex')+'@example.invalid';
    const result=await ok('StudioManagement/register','POST',{token:invite.token,email,fullName:'Access '+name,password});
    return {id:result.userId,name,email,password,invite};
}
async function activate(user) {
    await ok('StudioManagement/onboarding/'+user.id+'/complete','POST',{});
    const login=await ok('App/user','GET',undefined,{...basic(user.email,user.password),'X-Studio-Access-Token':user.invite.token});
    user.headers={'Content-Type':'application/json','Espo-Authorization':Buffer.from(user.email+':'+login.token).toString('base64'),'Espo-Authorization-By-Token':'true'};
    user.login=login;
    return user;
}
(async()=>{
    const noRole=await role('none');
    const simpleRole=await role('simple',{smProfileAccessPermission:'team',smPersonalAnalyticsPermission:'yes'});
    const fullRole=await role('full',{smProfileAccessPermission:'all'});
    const assignedRole=await role('assigned',{smAccountOnboardingPermission:'own'});
    const allRole=await role('hr',{smAccountOnboardingPermission:'all'});
    const denied=await activate(await account('denied',noRole));
    const simple=await activate(await account('simple',simpleRole));
    const full=await activate(await account('full',fullRole));
    const assigned=await activate(await account('assigned',assignedRole,'Model'));
    const other=await activate(await account('other',assignedRole));
    const hr=await activate(await account('hr',allRole));
    await ok('User/'+full.id,'PUT',{title:'Private job',smSharePercentOverride:'23',smProfileBio:'PRIVATE PROFILE'});
    const png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jf2QAAAAASUVORK5CYII=';
    const image=await ok('Attachment','POST',{name:'profile.png',type:'image/png',role:'Attachment',parentType:'User',field:'smProfilePhotos',file:'data:image/png;base64,'+png});
    await ok('User/'+full.id,'PUT',{smProfilePhotosIds:[image.id]});
    for(const actor of [denied,simple,full]) {
        for(const id of [actor.id,actor===full?denied.id:full.id]) {
            const response=await request('User/'+id,'GET',undefined,actor.headers);
            check(response.status===(actor===denied?403:200),actor.name+' own/other profile read');
            if(actor===simple) {
                const fields=Object.keys(response.data);
                check(fields.every(key=>['id','name','userName','avatarId','avatarColor'].includes(key)), 'Only identity fields in native simple profile');
            }
        }
        const summary=await request('StudioManagement/profiles/'+full.id,'GET',undefined,actor.headers);
        check(summary.status===(actor===denied?403:200),'Simple profile endpoint authorization');
        const edit=await request('User/'+actor.id,'PUT',{title:'Should require full'},actor.headers);
        check(edit.status===(actor===full?200:403),'Own profile editing requires full access');
        check((await request('User/'+full.id+'/stream','GET',undefined,actor.headers)).status===403,'Stream blocked');
        check((await request('GlobalStream','GET',undefined,actor.headers)).status===403,'Global stream blocked');
        check((await request('Note','POST',{post:'Disabled feed'},actor.headers)).status===403,'Creating feed posts is blocked');
    }
    check((await request('StudioManagement/personal-analytics','GET',undefined,denied.headers)).status===403,'Personal analytics denied');
    const analytics=await ok('StudioManagement/personal-analytics?userId='+full.id,'GET',undefined,simple.headers);
    check(analytics.personalOnly===true && !('totals'in analytics) && !('rows'in analytics),'Personal endpoint has no studio finances');
    check((await request('StudioManagement/analytics?from=2026-11-01&to=2026-11-07','GET',undefined,simple.headers)).status===403,'Personal grant does not grant studio access');
    check((await request('StudioManagement/staff/operator/'+full.id,'GET',undefined,simple.headers)).status===403,'Simple profile cannot load payroll history');
    check((await request('Attachment/'+image.id,'GET',undefined,simple.headers)).status===403,'Simple profile cannot read profile photos');
    const listed=await ok('User?maxSize=50','GET',undefined,simple.headers);
    check(listed.list.every(user=>!('smSharePercentOverride'in user)&&!('title'in user)),'User list does not expose full profiles');

    const pendingMine=await account('pending-mine',noRole,'Operator',assigned.id);
    const pendingOther=await account('pending-other',noRole,'Model',other.id);
    const unassigned=await account('pending-unassigned',noRole,'Operator');
    const assignedQueue=await ok('StudioManagement/onboarding','GET',undefined,assigned.headers);
    check(assignedQueue.list.some(u=>u.id===pendingMine.id),'Any account type can be assigned recruiter');
    check(!assignedQueue.list.some(u=>[pendingOther.id,unassigned.id].includes(u.id)),'Assigned queue excludes foreign and unassigned');
    for(const id of [pendingOther.id,unassigned.id]) {
        check((await request('StudioManagement/onboarding/'+id,'GET',undefined,assigned.headers)).status===404,'Foreign detail denied');
        check((await request('StudioManagement/onboarding/'+id+'/complete','POST',{},assigned.headers)).status===404,'Foreign activation denied');
    }
    check((await request('StudioManagement/onboarding','GET',undefined,denied.headers)).status===403,'No approval grant denies queue');
    const allQueue=await ok('StudioManagement/onboarding','GET',undefined,hr.headers);
    check([pendingMine.id,pendingOther.id,unassigned.id].every(id=>allQueue.list.some(u=>u.id===id)),'HR sees all queued accounts');
    const profile=await ok('StudioManagement/onboarding/'+pendingMine.id,'PUT',{smRecruiterRewardMode:'PercentPerShift'},assigned.headers);
    check(profile.smRecruiterRewardMode==='PercentPerShift','Recruiter can select percent payout');
    check((await request('StudioManagement/onboarding/'+pendingMine.id,'PUT',{smRecruiterRewardMode:'invalid'},assigned.headers)).status===400,'Invalid payout rejected');
    await ok('StudioManagement/onboarding/'+pendingMine.id+'/complete','POST',{smRecruiterRewardMode:'FlatAfterFiveShifts'},assigned.headers);
    const activated=await ok('User/'+pendingMine.id);
    check(activated.isActive && activated.smRecruiterRewardMode==='FlatAfterFiveShifts'&&Number(activated.smRecruiterFlatAmount)===150&&Number(activated.smRecruiterPercent)===5,'Payout and account activation persisted');
    check((await request('App/user','GET',undefined,{...basic(unassigned.email,unassigned.password),'X-Studio-Access-Token':unassigned.invite.token})).status===502,'Pending account remains blocked');
    await ok('StudioManagement/onboarding/'+unassigned.id+'/complete','POST',{},hr.headers);
    check((await ok('User/'+unassigned.id)).isActive,'HR can activate unassigned account');
    const session=await ok('App/user','GET',undefined,simple.headers);
    check(!Object.keys(session.user).some(key=>key.startsWith('sm')&&key!=='smAccountType'),'Session does not leak hidden profile attributes');
    await ok('User/'+simple.id,'PUT',{rolesIds:[noRole.id,simpleRole.id]});
    check((await request('StudioManagement/profiles/'+full.id,'GET',undefined,simple.headers)).status===200,'Multiple roles preserve the stronger profile grant');
    await ok('Role/'+simpleRole.id,'PUT',{smProfileAccessPermission:'no',smPersonalAnalyticsPermission:'no'});
    check((await request('User/'+simple.id,'GET',undefined,simple.headers)).status===403,'Profile grant revoked for active session');
    check((await request('StudioManagement/personal-analytics','GET',undefined,simple.headers)).status===403,'Personal analytics grant revoked for active session');
    await ok('Role/'+simpleRole.id,'PUT',{smProfileAccessPermission:'team',smPersonalAnalyticsPermission:'yes'});
    state.simple=simple;state.assigned=assigned;state.hr=hr;state.denied=denied;state.full=full;state.pendingId=pendingOther.id;
    fs.writeFileSync(stateFile,JSON.stringify(state));
    console.log('Profile and registration access HTTP: '+checks+' checks passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
