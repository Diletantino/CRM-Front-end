define(['view'], function (View) {
    return class extends View {
        template = 'studio-management:sm-analytics/index'

        setup() {
            this.addHandler('click', '[data-period]', event => {
                this.setPeriod(event.currentTarget.dataset.period);
                this.load();
            });
            this.addHandler('click', '[data-action="apply"]', () => this.load());
        }

        afterRender() {
            this.setPeriod('month');
            this.load();
        }

        iso(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        setPeriod(period) {
            const now = new Date();
            let from = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            if (period === 'week') {
                const mondayOffset = (from.getDay() + 6) % 7;
                from.setDate(from.getDate() - mondayOffset);
            } else if (period === 'month') {
                from = new Date(now.getFullYear(), now.getMonth(), 1);
            }

            this.$el.find('[name="from"]').val(this.iso(from));
            this.$el.find('[name="to"]').val(this.iso(now));
        }

        formatMoney(value, currency) {
            const number = Number(value || 0);

            try {
                return new Intl.NumberFormat(undefined, {style: 'currency', currency}).format(number);
            } catch (e) {
                return `${number.toFixed(2)} ${currency}`;
            }
        }

        async load() {
            const from = this.$el.find('[name="from"]').val();
            const to = this.$el.find('[name="to"]').val();
            const $status = this.$el.find('.analytics-status').text(this.translate('Loading...'));

            try {
                const data = await Espo.Ajax.getRequest('StudioManagement/analytics', {from, to});
                this.renderTotals(data.totals || []);
                this.renderRows(data.rows || []);
                $status.text('');
            } catch (e) {
                $status.text(
                    e?.xhr?.getResponseHeader('X-Status-Reason') ||
                    this.translate('analyticsFailed', 'messages', 'SmAnalytics')
                );
            }
        }

        renderTotals(totals) {
            const $container = this.$el.find('.analytics-totals').empty();

            if (!totals.length) {
                $('<p>').addClass('text-muted').text(this.translate('noData', 'messages', 'SmAnalytics')).appendTo($container);
                return;
            }

            totals.forEach(total => {
                const $panel = $('<div>').addClass('panel panel-default').css({display: 'inline-block', marginRight: '12px', minWidth: '260px'});
                const $body = $('<div>').addClass('panel-body').appendTo($panel);
                $('<strong>').text(total.currency).appendTo($body);
                $('<div>').text(`${this.translate('Gross', 'labels', 'SmAnalytics')}: ${this.formatMoney(total.totalGross, total.currency)}`).appendTo($body);
                $('<div>').text(`${this.translate('Studio', 'labels', 'SmAnalytics')}: ${this.formatMoney(total.studioShare, total.currency)}`).appendTo($body);
                $('<div>').text(`${this.translate('Producers', 'labels', 'SmAnalytics')}: ${this.formatMoney(total.producerShare, total.currency)}`).appendTo($body);
                $('<div>').text(`${this.translate('Participants', 'labels', 'SmAnalytics')}: ${this.formatMoney(total.participantShare, total.currency)}`).appendTo($body);
                $('<div>').addClass('text-muted').text(`${this.translate('Shifts', 'labels', 'SmAnalytics')}: ${total.shiftCount}`).appendTo($body);
                $panel.appendTo($container);
            });
        }

        renderRows(rows) {
            const $body = this.$el.find('.analytics-rows').empty();

            rows.forEach(row => {
                const $tr = $('<tr>');
                $('<td>').text(row.producerName).appendTo($tr);
                $('<td>').text(row.teamName).appendTo($tr);
                $('<td>').text(row.shiftCount).appendTo($tr);
                $('<td>').text(this.formatMoney(row.totalGross, row.currency)).appendTo($tr);
                $('<td>').text(this.formatMoney(row.studioShare, row.currency)).appendTo($tr);
                $('<td>').text(this.formatMoney(row.producerShare, row.currency)).appendTo($tr);
                $('<td>').text(this.formatMoney(row.participantShare, row.currency)).appendTo($tr);
                $tr.appendTo($body);
            });
        }
    };
});
