<div class="page-header clearfix">
    <h3 class="pull-left">{{title}}</h3>
    <button class="btn btn-default btn-sm pull-right" data-action="refresh">
        <span class="fas fa-sync-alt" aria-hidden="true"></span> {{labels.refresh}}
    </button>
</div>
<div class="panel panel-default">
    <div class="table-responsive">
        <table class="table table-striped table-hover table-condensed">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{labels.fullName}}</th>
                    <th>{{labels.birthDate}}</th>
                    {{#if isOperator}}<th>{{labels.contact}}</th>{{/if}}
                    <th>{{labels.photo}}</th>
                    <th>{{labels.startDate}}</th>
                    <th>{{labels.previousWeekAverage}}</th>
                    {{#if isOperator}}
                    <th>{{labels.currentWeekAverage}}</th>
                    <th>{{labels.currentWeekShifts}}</th>
                    {{else}}
                    <th>{{labels.team}}</th>
                    {{/if}}
                    <th>{{labels.lastShift}}</th>
                    <th>{{labels.action}}</th>
                </tr>
            </thead>
            <tbody class="staff-rows"></tbody>
        </table>
    </div>
</div>
<div class="staff-status text-danger"></div>
