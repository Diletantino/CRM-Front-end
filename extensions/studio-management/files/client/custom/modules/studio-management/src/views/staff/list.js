define(['view'], function (View) {
    return class extends View {
        template = 'studio-management:staff/list'

        setup() {
            this.staffType = this.options.staffType === 'operator' ? 'operator' : 'model';
            this.scope = this.staffType === 'operator' ? 'SmOperators' : 'SmModels';
            this.addHandler('click', '[data-action="refresh"]', () => this.load());
        }

        data() {
            const label = name => this.translate(name, 'labels', this.scope);

            return {
                isOperator: this.staffType === 'operator',
                title: this.translate(this.scope, 'scopeNames'),
                labels: {
                    fullName: label('Full Name'),
                    birthDate: label('Birth Date'),
                    contact: label('Contact'),
                    photo: label('Photo'),
                    startDate: label('Start Date'),
                    previousWeekAverage: label('Previous Week Average'),
                    currentWeekAverage: label('Current Week Average'),
                    currentWeekShifts: label('Current Week Shifts'),
                    team: label('Team'),
                    lastShift: label('Last Shift'),
                    action: label('Action'),
                    refresh: label('Refresh'),
                },
            };
        }

        afterRender() {
            this.load();
        }

        async load() {
            const $status = this.$el.find('.staff-status').text(this.translate('Loading...'));
            this.$el.find('[data-action="refresh"]').prop('disabled', true);

            try {
                const data = await Espo.Ajax.getRequest(`StudioManagement/staff/${this.staffType}`);
                this.renderRows(data.rows || []);
                $status.text('');
            } catch (error) {
                $status.text(
                    error?.xhr?.getResponseHeader('X-Status-Reason') ||
                    this.translate('loadFailed', 'messages', this.scope)
                );
            } finally {
                this.$el.find('[data-action="refresh"]').prop('disabled', false);
            }
        }

        renderRows(rows) {
            const $body = this.$el.find('.staff-rows').empty();
            const isOperator = this.staffType === 'operator';

            if (!rows.length) {
                $('<tr>').append(
                    $('<td>')
                        .attr('colspan', isOperator ? 11 : 9)
                        .addClass('text-muted text-center')
                        .text(this.translate('noData', 'messages', this.scope))
                ).appendTo($body);

                return;
            }

            rows.forEach((row, index) => {
                const $tr = $('<tr>');
                $('<td>').text(index + 1).appendTo($tr);
                $('<td>').append(
                    $('<a>').attr('href', `#User/view/${encodeURIComponent(row.id)}`).text(row.name || '—')
                ).appendTo($tr);
                $('<td>').text(this.formatDate(row.birthDate)).appendTo($tr);

                if (isOperator) {
                    $('<td>').text(row.contact || '—').appendTo($tr);
                }

                const $photo = $('<td>').addClass('text-center');

                if (row.hasAvatar) {
                    $('<img>')
                        .attr('src', `${this.getBasePath()}?entryPoint=avatar&size=small&id=${encodeURIComponent(row.id)}`)
                        .attr('alt', row.name || '')
                        .css({width: '38px', height: '38px', objectFit: 'cover', borderRadius: '4px'})
                        .appendTo($photo);
                } else {
                    $('<span>').addClass('fas fa-user text-muted').attr('aria-hidden', 'true').appendTo($photo);
                }

                $photo.appendTo($tr);
                $('<td>').text(this.formatDate(row.startDate)).appendTo($tr);
                $('<td>').text(this.formatAmount(row.previousWeekAverage)).appendTo($tr);

                if (isOperator) {
                    $('<td>').text(this.formatAmount(row.currentWeekAverage)).appendTo($tr);
                    $('<td>').text(row.currentWeekShiftCount || 0).appendTo($tr);
                } else {
                    $('<td>').text((row.teamNames || []).join(', ') || '—').appendTo($tr);
                }

                $('<td>').text(this.formatDateTime(row.lastShiftAt)).appendTo($tr);
                $('<td>').append(
                    $('<a>')
                        .addClass('btn btn-default btn-xs')
                        .attr('href', `#User/edit/${encodeURIComponent(row.id)}`)
                        .text(this.translate('Edit', 'labels', this.scope))
                ).appendTo($tr);
                $tr.appendTo($body);
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
    };
});
