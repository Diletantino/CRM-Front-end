define(['controller'], function (Controller) {
    return class extends Controller {
        actionRegister(options) {
            this.entire('studio-management:views/registration', options || {}, view => {
                view.render();

                view.on('login', (userName, data) => {
                    this.baseController.trigger(
                        'login',
                        this.baseController.normalizeLoginData(userName, data)
                    );
                });
            });
        }
    };
});
