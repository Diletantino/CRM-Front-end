define(['views/record/detail'], function (DetailRecordView) {
    return class extends DetailRecordView {
        setupActionItems() {
            super.setupActionItems();

            if (!this.getAcl().checkModel(this.model, 'edit')) {
                return;
            }

            this.dropdownItemList.push({
                label: 'Open Shift',
                name: 'openShift',
                onClick: () => this.actionOpenShift(),
                iconClass: 'fas fa-play',
            });
            this.dropdownItemList.push({
                label: 'Close Shift',
                name: 'closeShift',
                onClick: () => this.actionCloseShift(),
                iconClass: 'fas fa-stop',
            });

            const control = () => {
                const status = this.model.get('status');
                status === 'Draft' ? this.showActionItem('openShift') : this.hideActionItem('openShift');
                status === 'Open' ? this.showActionItem('closeShift') : this.hideActionItem('closeShift');
            };

            control();
            this.model.onSync({owner: this, callback: control});
        }

        async actionOpenShift() {
            await this.confirm(this.translate('openShiftConfirmation', 'messages', 'SmShift'));
            const data = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/open`);
            this.model.set(data);
            Espo.Ui.success(this.translate('shiftOpened', 'messages', 'SmShift'));
        }

        async actionCloseShift() {
            const gross = window.prompt(this.translate('enterGross', 'messages', 'SmShift'));

            if (gross === null) {
                return;
            }

            const metricsRaw = window.prompt(this.translate('enterPlatformMetrics', 'messages', 'SmShift'), '{}');

            if (metricsRaw === null) {
                return;
            }

            let platformMetrics;

            try {
                platformMetrics = JSON.parse(metricsRaw || '{}');
            } catch (e) {
                Espo.Ui.error(this.translate('invalidMetricsJson', 'messages', 'SmShift'));
                return;
            }

            if (!platformMetrics || Array.isArray(platformMetrics) || typeof platformMetrics !== 'object') {
                Espo.Ui.error(this.translate('invalidMetricsJson', 'messages', 'SmShift'));
                return;
            }

            await this.confirm(this.translate('closeShiftConfirmation', 'messages', 'SmShift'));
            const data = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/close`, {
                totalGross: gross,
                platformMetrics,
            });
            this.model.set(data);
            Espo.Ui.success(this.translate('shiftClosed', 'messages', 'SmShift'));
        }
    };
});
