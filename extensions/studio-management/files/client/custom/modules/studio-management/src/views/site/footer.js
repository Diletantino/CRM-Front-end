define(['view'], function (View) {
    return class extends View {
        templateContent = ' '

        setup() {
            // Do not use the bundled site/footer template or Espo resource cache.
            this.wait(this.loadLocalTemplate());
        }

        async loadLocalTemplate() {
            try {
                const response = await fetch(this.getBasePath() + 'client/res/templates/site/footer.tpl', {
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    throw new Error('Footer template HTTP ' + response.status);
                }
                const source = await response.text();
                if (source.includes('\0')) {
                    throw new Error('Footer template contains NUL bytes');
                }
                this.templateContent = source;
                this.setTemplateContent(source);
            } catch (error) {
                // A missing footer must not prevent the CRM itself from opening.
                console.error('Could not load local footer template.', error);
                this.templateContent = '';
                this.setTemplateContent('');
            }
        }
    };
});
