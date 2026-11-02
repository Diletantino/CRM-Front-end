define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return this.getAcl().check('SmAnalytics') ||
                ['Operator', 'Model'].includes(this.getUser().get('smAccountType'));
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/sm-analytics/index');
        }

        actionShow(options) {
            this.actionIndex(options);
        }
    };
});
