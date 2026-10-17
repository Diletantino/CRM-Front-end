define(['views/edit'], function (EditView) {
    return class extends EditView {
        setup() {
            this.optionsToPass = [...this.optionsToPass, 'params'];
            super.setup();
        }
    };
});
