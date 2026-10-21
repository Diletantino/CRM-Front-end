define(['views/fields/link'], function (LinkFieldView) {
    return class extends LinkFieldView {
        setup() {
            this.autocompleteOnEmpty = true;
            super.setup();
            this.createDisabled = true;
            this.selectRecordsView = 'studio-management:views/sm-registration-link/select-roles';
        }

        async getAutocompleteUrl(query) {
            const url = await super.getAutocompleteUrl(query);
            return url.replace(/^Role(?=\\?|$)/, 'StudioManagement/registrationRoles');
        }
    };
});
