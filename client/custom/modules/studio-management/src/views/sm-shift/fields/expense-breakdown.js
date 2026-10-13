define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:sm-shift/fields/expense-breakdown/detail'
        listTemplate = 'studio-management:sm-shift/fields/expense-breakdown/detail'

        data() {
            const data = super.data();
            const value = this.model.get(this.name);

            return {
                ...data,
                displayValue: Array.isArray(value) && value.length ?
                    JSON.stringify(value, null, 2) :
                    '',
            };
        }
    };
});
