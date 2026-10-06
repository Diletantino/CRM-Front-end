define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:sm-shift/fields/site-data/detail'
        listTemplate = 'studio-management:sm-shift/fields/site-data/detail'
        editTemplate = 'studio-management:sm-shift/fields/site-data/edit'

        data() {
            const data = super.data();
            const status = this.model.get('status') || 'Draft';
            const accountType = this.getUser().get('smAccountType');
            const isManager = this.getUser().isAdmin() || accountType === 'ProducerAdmin';
            const isAssignedOperator = accountType === 'Operator' &&
                this.model.get('operatorId') === this.getUser().id;
            const rows = this.getRows().map((row, index) => ({
                ...row,
                index,
                stateLabel: this.translate(row.state || 'Pending', 'siteStates', 'SmShift'),
                started: row.state === 'Started',
                banned: row.state === 'Banned',
            }));

            return {
                ...data,
                rows,
                hasRows: rows.length > 0,
                showFinancial: ['Counting', 'Closed'].includes(status),
                canEditCredentials: status === 'Draft' || isManager,
                canEditFinancial: status === 'Counting' || (status === 'Closed' && isManager),
                canManageState: status === 'Open' &&
                    (isAssignedOperator || this.getUser().isAdmin()) &&
                    this.getAcl().checkModel(this.model, 'edit'),
                canAddRemove: status === 'Draft' || isManager,
            };
        }

        afterRender() {
            super.afterRender();

            this.$el.off('.smSiteData');
            this.$el.on('click.smSiteData', '[data-action="add-site-row"]', () => this.addRow());
            this.$el.on('click.smSiteData', '[data-action="remove-site-row"]', event => {
                this.removeRow(Number(event.currentTarget.dataset.index));
            });
            this.$el.on('click.smSiteData', '[data-action="set-site-state"]', event => {
                this.setSiteState(
                    Number(event.currentTarget.dataset.index),
                    String(event.currentTarget.dataset.state || 'Pending')
                );
            });
        }

        fetch() {
            return {[this.name]: this.readRows()};
        }

        getRows() {
            const value = this.model.get(this.name);

            if (!Array.isArray(value)) {
                return [];
            }

            return value.map(row => ({
                site: String(row?.site || ''),
                login: String(row?.login || ''),
                password: String(row?.password || ''),
                state: String(row?.state || 'Pending'),
                earning: String(row?.earning ?? '0.00'),
                offlineBonus: String(row?.offlineBonus ?? '0.00'),
            }));
        }

        readRows() {
            const rows = this.getRows();

            this.$el.find('tr[data-index]').each((index, element) => {
                const rowIndex = Number(element.dataset.index);
                const row = rows[rowIndex] || {
                    site: '',
                    login: '',
                    password: '',
                    state: 'Pending',
                    earning: '0.00',
                    offlineBonus: '0.00',
                };

                element.querySelectorAll('[data-field]').forEach(input => {
                    row[input.dataset.field] = input.value;
                });
                rows[rowIndex] = row;
            });

            return rows;
        }

        addRow() {
            const rows = this.readRows();
            rows.push({
                site: '',
                login: '',
                password: '',
                state: 'Pending',
                earning: '0.00',
                offlineBonus: '0.00',
            });
            this.model.set(this.name, rows);
            this.reRender();
        }

        removeRow(index) {
            const rows = this.readRows();
            rows.splice(index, 1);
            this.model.set(this.name, rows);
            this.reRender();
        }

        async setSiteState(index, state) {
            const rows = this.getRows();

            if (!rows[index] || !['Started', 'Banned'].includes(state)) {
                return;
            }

            rows[index].state = state;
            Espo.Ui.notify(this.translate('saving', 'messages'));

            try {
                await this.model.save({[this.name]: rows}, {patch: true});
                Espo.Ui.success(this.translate('Saved'));
                this.reRender();
            } catch (error) {
                Espo.Ui.error(error?.message || this.translate('Error'));
            }
        }
    };
});
