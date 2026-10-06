define(['views/fields/base'], function (BaseFieldView) {
    return class extends BaseFieldView {
        detailTemplate = 'studio-management:user/fields/staff-dashboard/detail'
        editTemplate = 'studio-management:user/fields/staff-dashboard/detail'

        afterRender() {
            this.load();
        }

        getStaffType() {
            if (this.model.get('smAccountType') === 'Model') {
                return 'model';
            }

            if (this.model.get('smAccountType') === 'Operator') {
                return 'operator';
            }

            return null;
        }

        async load() {
            const type = this.getStaffType();

            if (!type || !this.model.id) {
                this.$el.empty();
                return;
            }

            const $status = this.$el.find('.staff-dashboard-status').text(this.translate('Loading...'));

            try {
                const data = await Espo.Ajax.getRequest(
                    `StudioManagement/staff/${type}/${encodeURIComponent(this.model.id)}`
                );
                this.renderHistory(data.history || [], type);
                this.renderChart(data.chart || []);
                $status.text('');
            } catch (error) {
                $status.text(
                    error?.xhr?.getResponseHeader('X-Status-Reason') ||
                    this.translate('staffProfileUnavailable', 'messages', 'User')
                );
            }
        }

        renderHistory(rows, type) {
            const $head = this.$el.find('.staff-history-head').empty();
            const labels = type === 'model'
                ? ['#', 'Operator', 'Start', 'End', 'Result', 'Personal Income']
                : ['#', 'Team', 'Start', 'End', 'Result', 'Personal Income'];

            labels.forEach(label => $('<th>')
                .text(label === '#' ? '#' : this.translate(label, 'labels', 'User'))
                .appendTo($head));

            const $body = this.$el.find('.staff-history-rows').empty();

            if (!rows.length) {
                $('<tr>').append(
                    $('<td>')
                        .attr('colspan', 6)
                        .addClass('text-center text-muted')
                        .text(this.translate('noStaffShifts', 'messages', 'User'))
                ).appendTo($body);
                return;
            }

            rows.forEach(row => {
                const $tr = $('<tr>');
                $('<td>').append(
                    $('<a>').attr('href', `#SmShift/view/${encodeURIComponent(row.id)}`).text(row.number || '—')
                ).appendTo($tr);
                $('<td>').text(type === 'model' ? row.operatorName : row.teamName).appendTo($tr);
                $('<td>').text(this.formatDateTime(row.startTime)).appendTo($tr);
                $('<td>').text(this.formatDateTime(row.endTime)).appendTo($tr);
                $('<td>').text(this.formatMoney(row.result, row.currency)).appendTo($tr);
                $('<td>').text(this.formatMoney(row.share, row.currency)).appendTo($tr);
                $tr.appendTo($body);
            });
        }

        renderChart(rows) {
            const $chart = this.$el.find('.staff-income-chart').empty();
            const visibleRows = rows.slice(-30);

            if (!visibleRows.length) {
                $('<p>').addClass('text-muted').text(this.translate('noStaffShifts', 'messages', 'User')).appendTo($chart);
                return;
            }

            const max = Math.max(...visibleRows.map(row => Number(row.amount || 0)), 1);

            visibleRows.forEach(row => {
                const amount = Number(row.amount || 0);
                const $line = $('<div>').css({display: 'flex', alignItems: 'center', marginBottom: '6px'});
                $('<span>').css({width: '92px', flexShrink: 0}).text(this.formatDate(row.date)).appendTo($line);
                const $track = $('<span>').css({height: '16px', background: 'var(--gray-soft, #e9ecef)', flex: 1, borderRadius: '3px', overflow: 'hidden'});
                $('<span>').css({display: 'block', width: `${Math.max(2, amount / max * 100)}%`, height: '100%', background: '#5cb85c'}).appendTo($track);
                $track.appendTo($line);
                $('<strong>').css({width: '100px', marginLeft: '10px', textAlign: 'right'}).text(this.formatAmount(amount)).appendTo($line);
                $line.appendTo($chart);
            });
        }

        formatDate(value) {
            return value ? this.getDateTime().toDisplayDate(value) : '—';
        }

        formatDateTime(value) {
            return value ? this.getDateTime().toDisplay(value) : '—';
        }

        formatAmount(value) {
            return new Intl.NumberFormat(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})
                .format(Number(value || 0));
        }

        formatMoney(value, currency) {
            const amount = Number(value || 0);

            try {
                return new Intl.NumberFormat(undefined, {style: 'currency', currency: currency || 'USD'}).format(amount);
            } catch (e) {
                return `${this.formatAmount(amount)} ${currency || ''}`.trim();
            }
        }
    };
});
