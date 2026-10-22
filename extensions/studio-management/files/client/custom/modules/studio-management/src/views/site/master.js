define(['views/site/master'], function (MasterView) {
    return class extends MasterView {
        setup() {
            this.views = {
                ...this.views,
                footer: {
                    ...this.views.footer,
                    view: 'studio-management:views/site/footer',
                },
            };
            super.setup();
        }

        afterRender() {
            const footer = this.getView('footer');
            const templateHtml = footer?.$el.html();

            // Keep the standard layout, maintenance and 2FA lifecycle intact.
            super.afterRender();

            // The deployment's editable template is authoritative for this view.
            if (footer && typeof templateHtml === 'string') {
                footer.$el.html(templateHtml);
            }
        }
    };
});
