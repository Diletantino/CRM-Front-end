define(['controllers/home'], function (HomeController) {
    return class extends HomeController {
        actionIndex() {
            if (!this.getUser().isAdmin() && this.getUser().get('smAccountType') === 'Recruiter') {
                this.getRouter().navigate('#SmAccountOnboarding', {trigger: true});
                return;
            }
            super.actionIndex();
        }
    };
});
