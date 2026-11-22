const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
let checks = 0;
for (const prefix of ['', 'extensions/studio-management/files/']) {
    for (const file of [
        'client/custom/modules/studio-management/src/controllers/sm-credits.js',
        'client/custom/modules/studio-management/src/views/sm-credits/index.js',
        'client/custom/modules/studio-management/src/views/site/credits-item.js',
        'custom/Espo/Modules/StudioManagement/Resources/metadata/scopes/SmCredits.json',
        'custom/Espo/Modules/StudioManagement/Resources/metadata/clientDefs/SmCredits.json',
    ]) {
        assert(!fs.existsSync(path.join(root,prefix,file)),file+' must be removed'); checks++;
    }
}
const footer=fs.readFileSync(path.join(root,'client/res/templates/site/footer.tpl'),'utf8');
assert(footer.includes('EspoCRM')); checks++;
assert(fs.existsSync(path.join(root,'LICENSE.txt'))); checks++;
console.log('Credits removal: '+checks+' checks passed.');
