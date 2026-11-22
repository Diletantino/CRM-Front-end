define(['controller', 'studio-management:role-policy'], function (Controller, RolePolicy) {
    return class extends Controller {
        checkAccess() {
            return this.getAcl().check('SmAnalytics') &&
                RolePolicy.level(this, 'smAnalyticsAccess', 'legacy') !== 'no';
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
