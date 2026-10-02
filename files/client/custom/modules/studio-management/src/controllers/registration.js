define(['controller'], function (Controller) {
    return class extends Controller {
        actionRegister(options) {
            this.entire('studio-management:views/registration', options || {}, view => view.render());
        }
    };
});
