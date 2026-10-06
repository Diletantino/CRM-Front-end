define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return this.getAcl().check('SmOperators');
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/staff/list', {staffType: 'operator'});
        }

        actionShow(options) {
            this.actionIndex(options);
        }
    };
});
