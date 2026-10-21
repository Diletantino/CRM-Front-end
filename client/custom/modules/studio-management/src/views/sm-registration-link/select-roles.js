define(['views/modals/select-records'], function (SelectRecordsView) {
    return class extends SelectRecordsView {
        setupSearch() {
            this.collection.url = 'StudioManagement/registrationRoles';
            super.setupSearch();
        }
    };
});
