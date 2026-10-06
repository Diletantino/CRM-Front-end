define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return this.getAcl().check('SmModels');
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/staff/list', {staffType: 'model'});
        }

        actionShow(options) {
            this.actionIndex(options);
        }
    };
});
