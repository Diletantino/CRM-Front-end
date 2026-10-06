define(['views/record/list'], function (ListRecordView) {
    return class extends ListRecordView {
        rowActionsView = 'views/record/row-actions/empty'

        async actionFinishShift(data) {
            const id = data.id;
            const model = id ? this.collection.get(id) : null;

            if (!model) {
                return;
            }

            await this.confirm(this.translate('finishShiftConfirmation', 'messages', 'SmShift'));
            const response = await Espo.Ajax.postRequest(`SmShift/${id}/finish`);
            model.set(response);
            this.getRouter().navigate(`#SmShift/edit/${id}/count`, {trigger: true});
        }
    };
});
