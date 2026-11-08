define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return this.getUser().isAdmin() || this.getUser().get('smAccountType') === 'Recruiter';
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/sm-account-onboarding/index');
        }
    };
});
