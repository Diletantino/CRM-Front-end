define(['controller'], function (Controller) {
    return class extends Controller {
        async actionRegister(options) {
            // Recover old entry-point URLs after login, but never override an explicitly opened link.
            if (!this.hasPersonalLinkToken() && this.getStorage().get('user', 'auth')) {
                try {
                    const data = await Espo.Ajax.getRequest('App/user');
                    if (data?.user?.id) {
                        this.openCrm();
                        return;
                    }
                } catch (error) {
                    // A stale session is not a login bypass. Keep the personal-link page.
                    if (error?.xhr) {
                        error.xhr.errorIsHandled = true;
                    }
                }
            }

            this.entire('studio-management:views/registration', options || {}, view => {
                view.once('login', (userName, data) => {
                    this.baseController.trigger(
                        'login',
                        this.baseController.normalizeLoginData(userName, data)
                    );
                    // The core login listener stores the session synchronously. Boot the normal
                    // application next: the entry-point app has no router and must not survive F5.
                    this.openCrm();
                });

                view.render();
            });
        }

        hasPersonalLinkToken() {
            const rawFragment = window.location.hash.replace(/^#/, '');
            const fragment = new URLSearchParams(rawFragment);
            const query = new URLSearchParams(window.location.search);
            if (
                fragment.has('access') ||
                /^[A-Za-z0-9_-]{32}$/.test(rawFragment) ||
                /^[a-f0-9]{64}$/.test(rawFragment) ||
                query.has('token')
            ) {
                return true;
            }
            try {
                return Boolean(window.sessionStorage.getItem('studioAccessToken'));
            } catch (error) {
                return false;
            }
        }

        openCrm() {
            // Same-origin installation path only: no entryPoint, query token or secret fragment.
            window.location.replace(window.location.pathname);
        }
    };
});
