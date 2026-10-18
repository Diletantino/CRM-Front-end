define(['views/login'], function (LoginView) {
    return class extends LoginView {
        template = 'studio-management:admin-login'

        setup() {
            super.setup();

            this.adminLoginAllowed = false;
            this.wait(
                Espo.Ajax.getRequest('StudioManagement/access-policy')
                    .then(data => this.adminLoginAllowed = !!data.adminLoginAllowed)
                    .catch(() => this.adminLoginAllowed = false)
            );
        }

        data() {
            return {
                ...super.data(),
                adminLoginAllowed: this.adminLoginAllowed,
            };
        }

        afterRender() {
            if (!this.adminLoginAllowed) {
                return;
            }

            super.afterRender();
        }
    };
});
