define(['controller', 'studio-management:role-policy'], function (Controller, RolePolicy) {
    return class extends Controller {
        checkAccess() {
            return ['yes', 'all'].includes(RolePolicy.level(this, 'smPersonalAnalytics'));
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/sm-personal-analytics/index');
        }

        actionShow() {
            this.actionIndex();
        }
    };
});
