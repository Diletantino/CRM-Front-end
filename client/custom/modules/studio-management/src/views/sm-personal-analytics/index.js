define(['studio-management:views/sm-analytics/index'], function (AnalyticsView) {
    return class extends AnalyticsView {
        personalPage = true

        afterRender() {
            this.$el.find('[data-analytics-tab="overview"]').parent().hide();
            this.$el.find('[data-analytics-panel="overview"]').hide();
            this.load();
        }
    };
});
