<div class="page-header"><h3>{{translate 'SmAnalytics' category='scopeNames'}}</h3></div>
<div class="panel panel-default">
    <div class="panel-body">
        <div class="btn-group" style="margin-right: 12px">
            <button class="btn btn-default" data-period="today">{{translate 'Today' scope='SmAnalytics'}}</button>
            <button class="btn btn-default" data-period="week">{{translate 'Week' scope='SmAnalytics'}}</button>
            <button class="btn btn-default" data-period="month">{{translate 'Month' scope='SmAnalytics'}}</button>
        </div>
        <label style="margin-right: 8px">{{translate 'From' scope='SmAnalytics'}} <input type="date" class="form-control input-sm" name="from" style="display:inline-block;width:auto"></label>
        <label style="margin-right: 8px">{{translate 'To' scope='SmAnalytics'}} <input type="date" class="form-control input-sm" name="to" style="display:inline-block;width:auto"></label>
        <button class="btn btn-primary btn-sm" data-action="apply">{{translate 'Apply'}}</button>
        <span class="analytics-status text-danger" style="margin-left: 12px"></span>
    </div>
</div>
<div class="analytics-totals"></div>
<div class="panel panel-default">
    <div class="panel-heading"><strong>{{translate 'Producer Earnings' scope='SmAnalytics'}}</strong></div>
    <div class="table-responsive">
        <table class="table table-striped table-condensed">
            <thead><tr>
                <th>{{translate 'Producer' scope='SmAnalytics'}}</th>
                <th>{{translate 'Team'}}</th>
                <th>{{translate 'Shifts' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Gross' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Studio' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Producer Share' scope='SmAnalytics'}}</th>
                <th>{{translate 'Participants' category='labels' scope='SmAnalytics'}}</th>
            </tr></thead>
            <tbody class="analytics-rows"></tbody>
        </table>
    </div>
</div>
