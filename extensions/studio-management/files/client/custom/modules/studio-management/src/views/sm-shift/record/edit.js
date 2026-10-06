define(['views/record/edit'], function (EditRecordView) {
    return class extends EditRecordView {
        saveAndContinueEditingAction = false
        saveAndNewAction = false

        setup() {
            super.setup();

            const params = this.options.params || {};
            const status = this.model.get('status');
            const saveButton = this.buttonList.find(item => item.name === 'save');

            if (status && status !== 'Draft') {
                [
                    'name',
                    'operator',
                    'model',
                    'modelBirthDate',
                    'team',
                    'modelImages',
                    'currency',
                    'description',
                ].forEach(field => this.setFieldReadOnly(field, true));
            }

            if (!saveButton || this.isNew) {
                return;
            }

            if (status === 'Draft' && params.start) {
                saveButton.label = 'Start Shift';
                saveButton.style = 'success';
            } else if (status === 'Counting' && params.count) {
                saveButton.label = 'Complete Calculation';
                saveButton.style = 'success';
            } else if (status === 'Closed' && params.revise) {
                saveButton.label = 'Save Changes';
                saveButton.style = 'primary';
            }
        }

        async actionSave(data) {
            const params = this.options.params || {};
            const status = this.model.get('status');

            if (this.isNew || (status === 'Draft' && !params.start)) {
                return super.actionSave(data);
            }

            this.model.setMultiple(this.fetch());

            if (status === 'Draft' && params.start) {
                try {
                    await this.save(data?.options);
                    const response = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/open`);
                    this.model.set(response);
                    Espo.Ui.success(this.translate('shiftOpened', 'messages', 'SmShift'));
                    this.leaveToDetail();
                } catch (error) {
                    return Promise.reject(error);
                }

                return;
            }

            if (status === 'Counting' && params.count) {
                await this.confirm(this.translate('closeShiftConfirmation', 'messages', 'SmShift'));

                const response = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/close`, {
                    siteData: this.model.get('siteData') || [],
                    screenshotsIds: this.model.get('screenshotsIds') || [],
                });
                this.model.set(response);
                Espo.Ui.success(this.translate('shiftClosed', 'messages', 'SmShift'));
                this.leaveToDetail();

                return;
            }

            if (status === 'Closed' && params.revise && this.isManager()) {
                await this.confirm(this.translate('reviseShiftConfirmation', 'messages', 'SmShift'));

                const response = await Espo.Ajax.postRequest(`SmShift/${this.model.id}/revise`, {
                    siteData: this.model.get('siteData') || [],
                    screenshotsIds: this.model.get('screenshotsIds') || [],
                });
                this.model.set(response);
                Espo.Ui.success(this.translate('shiftRevised', 'messages', 'SmShift'));
                this.leaveToDetail();

                return;
            }

            return super.actionSave(data);
        }

        isManager() {
            return this.getUser().isAdmin() || this.getUser().get('smAccountType') === 'ProducerAdmin';
        }

        leaveToDetail() {
            this.setIsNotChanged();
            this.getRouter().navigate(`#SmShift/view/${this.model.id}`, {trigger: true});
        }
    };
});
