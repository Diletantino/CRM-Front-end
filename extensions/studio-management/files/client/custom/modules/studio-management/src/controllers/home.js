define(['controllers/home', 'studio-management:role-policy'], function (HomeController, RolePolicy) {
    return class extends HomeController {
        actionIndex() {
            if (!this.getUser().isAdmin() && this.getUser().get('smAccountType') === 'Recruiter' &&
                RolePolicy.canAccessOnboarding(this)) {
                this.getRouter().navigate('#SmAccountOnboarding', {trigger: true});
                return;
            }
            super.actionIndex();
        }
    };
});
