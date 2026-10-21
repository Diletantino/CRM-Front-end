define(['view'], function (View) {
    return class extends View {
        template = 'studio-management:sm-credits/index'

        updatePageTitle() {
            this.setPageTitle('Credits');
        }
    };
});
