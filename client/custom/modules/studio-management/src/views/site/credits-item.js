define(['views/site/navbar/item'], function (NavbarItemView) {
    return class extends NavbarItemView {
        template = 'studio-management:site/credits-item'

        afterRender() {
            this.positionCredit = () => this.updateCreditPosition();
            window.addEventListener('resize', this.positionCredit);
            this.resizeObserver = new ResizeObserver(this.positionCredit);
            const sidebar = document.querySelector('#navbar .navbar-left-container');
            if (sidebar) {
                this.resizeObserver.observe(sidebar);
            }
            this.bodyObserver = new MutationObserver(this.positionCredit);
            this.bodyObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class', 'data-navbar', 'data-direction'],
            });
            this.updateCreditPosition();
        }

        updateCreditPosition() {
            // Keep the native notice intact; only position it in the sidebar.
            const footer = document.querySelector('body > footer');
            if (!footer) {
                return;
            }
            this.footerElement = footer;
            const sidebar = document.querySelector('#navbar .navbar-left-container');
            if (!sidebar || document.body.getAttribute('data-navbar') !== 'side') {
                this.restoreCredit();
                return;
            }
            const rect = sidebar.getBoundingClientRect();
            const minimizer = sidebar.querySelector('.minimizer');
            const minimizerRect = minimizer?.getBoundingClientRect();
            const bottom = Math.min(rect.bottom, window.innerHeight,
                minimizerRect?.height > 0 ? minimizerRect.top : window.innerHeight);
            const height = 64;
            if (window.innerWidth < 760 || rect.width < 45 || rect.height < height ||
                rect.left < 0 || rect.right > window.innerWidth || bottom - height < rect.top) {
                this.restoreCredit();
                return;
            }
            footer.classList.add('studio-credit-in-sidebar');
            footer.classList.toggle('studio-credit-compact', rect.width < 100);
            footer.style.setProperty('--studio-credit-left', rect.left + 'px');
            footer.style.setProperty('--studio-credit-top', (bottom - height) + 'px');
            footer.style.setProperty('--studio-credit-width', rect.width + 'px');
            footer.style.setProperty('--studio-credit-height', height + 'px');
        }

        restoreCredit() {
            const footer = this.footerElement;
            if (!footer) {
                return;
            }
            footer.classList.remove('studio-credit-in-sidebar');
            footer.classList.remove('studio-credit-compact');
            for (const name of ['left', 'top', 'width', 'height']) {
                footer.style.removeProperty('--studio-credit-' + name);
            }
        }

        onRemove() {
            window.removeEventListener('resize', this.positionCredit);
            this.resizeObserver?.disconnect();
            this.bodyObserver?.disconnect();
            this.restoreCredit();
        }
    };
});
