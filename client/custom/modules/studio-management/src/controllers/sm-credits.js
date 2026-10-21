define(['controller'], function (Controller) {
    return class extends Controller {
        checkAccess() {
            return Boolean(this.getUser().id);
        }

        actionIndex() {
            this.handleCheckAccess('');
            this.main('studio-management:views/sm-credits/index');
        }

        actionShow() {
            this.actionIndex();
        }
    };
});
