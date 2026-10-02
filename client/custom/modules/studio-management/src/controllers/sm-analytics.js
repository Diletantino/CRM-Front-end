define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return this.getAcl().check('SmAnalytics');
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
