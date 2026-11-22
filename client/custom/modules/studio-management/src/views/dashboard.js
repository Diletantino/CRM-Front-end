define(['views/dashboard'], function (Dashboard) {
    return class extends Dashboard {
        setupCurrentTabLayout() {
            super.setupCurrentTabLayout();
            this.currentTabLayout = this.currentTabLayout.filter(item => item.name !== 'Stream');
        }
    };
});
