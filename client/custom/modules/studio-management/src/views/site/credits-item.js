define(['views/site/navbar/item'], function (NavbarItemView) {
    return class extends NavbarItemView {
        template = 'studio-management:site/credits-item'

        afterRender() {
            this.positionCredit = () => this.updateCreditPosition();
            window.addEventListener('resize', this.positionCredit);
            this.resizeObserver = new ResizeObserver(this.positionCredit);
            this.resizeObserver.observe(this.element);
            const navbar = document.getElementById('navbar');
            if (navbar) {
                this.resizeObserver.observe(navbar);
            }
            this.bodyObserver = new MutationObserver(this.positionCredit);
            this.bodyObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class', 'data-navbar', 'data-direction'],
            });
            this.updateCreditPosition();
        }

        updateCreditPosition() {
            // Reposition the real, unchanged native notice. Never hide or duplicate it,
            // and do not modify EspoCRM's legal-notice checks.
            const footer = document.querySelector('body > footer');
            const slot = this.element.querySelector('.studio-native-credit-slot');
            if (!footer || !slot) {
                return;
            }
            this.footerElement = footer;
            const rect = slot.getBoundingClientRect();
            if (window.innerWidth < 760 || rect.width < 1 || rect.height < 1 ||
                rect.left < 0 || rect.right > window.innerWidth || rect.top < 0) {
                this.restoreCredit();
                return;
            }
            footer.classList.add('studio-credit-in-navigation');
            footer.style.setProperty('--studio-credit-left', rect.left + 'px');
            footer.style.setProperty('--studio-credit-top', rect.top + 'px');
            footer.style.setProperty('--studio-credit-width', rect.width + 'px');
            footer.style.setProperty('--studio-credit-height', rect.height + 'px');
        }

        restoreCredit() {
            const footer = this.footerElement;
            if (!footer) {
                return;
            }
            footer.classList.remove('studio-credit-in-navigation');
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
