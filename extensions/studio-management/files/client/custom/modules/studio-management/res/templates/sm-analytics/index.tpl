<div class="page-header"><h3 class="analytics-heading">{{translate 'SmAnalytics' category='scopeNames'}}</h3></div>
<p class="analytics-status text-danger" role="status"></p>
<ul class="nav nav-tabs" role="tablist" style="margin-bottom: 16px;">
    <li class="active"><a href="#" role="tab" aria-selected="true" data-analytics-tab="overview">{{translate 'Overview' scope='SmAnalytics'}}</a></li>
    <li style="display:none"><a href="#" role="tab" aria-selected="false" data-analytics-tab="average">{{translate 'Weekly Average' scope='SmAnalytics'}}</a></li>
    <li style="display:none"><a href="#" role="tab" aria-selected="false" data-analytics-tab="hours">{{translate 'Weekly Hours' scope='SmAnalytics'}}</a></li>
    <li style="display:none"><a href="#" role="tab" aria-selected="false" data-analytics-tab="lastDate">{{translate 'Last Shift Date' scope='SmAnalytics'}}</a></li>
    <li style="display:none"><a href="#" role="tab" aria-selected="false" data-analytics-tab="lastCheck">{{translate 'Last Shift Check' scope='SmAnalytics'}}</a></li>
    <li style="display:none"><a href="#" role="tab" aria-selected="false" data-analytics-tab="earnings">{{translate 'Weekly Earnings' scope='SmAnalytics'}}</a></li>
</ul>
<div data-analytics-panel="overview">
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
    </div>
</div>
<div class="alert alert-info analytics-personal" style="display:block"></div>
<div class="analytics-totals"></div>
<div class="panel panel-default">
    <div class="panel-heading"><strong>{{translate 'Producer Earnings' scope='SmAnalytics'}}</strong></div>
    <div class="table-responsive">
        <table class="table table-striped table-condensed">
            <thead><tr>
                <th>{{translate 'Producer' scope='SmAnalytics'}}</th>
                <th>{{translate 'Team' scope='SmAnalytics'}}</th>
                <th>{{translate 'Shifts' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Gross' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Studio' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Producer Share' scope='SmAnalytics'}}</th>
                <th>{{translate 'Participants' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Technical Directors' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Recruiters' category='labels' scope='SmAnalytics'}}</th>
                <th>{{translate 'Expenses' category='labels' scope='SmAnalytics'}}</th>
            </tr></thead>
            <tbody class="analytics-rows"></tbody>
        </table>
    </div>
</div>
</div>
<div data-analytics-panel="average" class="panel panel-default" role="tabpanel" style="display:none">
    <div class="panel-heading">{{translate 'Weekly Average' scope='SmAnalytics'}}</div>
    <div class="panel-body"><p class="text-muted analytics-week-range"></p><div class="analytics-average h3"></div><p class="text-muted">{{translate 'checkScope' category='messages' scope='SmAnalytics'}}</p></div>
</div>
<div data-analytics-panel="hours" class="panel panel-default" role="tabpanel" style="display:none">
    <div class="panel-heading">{{translate 'Weekly Hours' scope='SmAnalytics'}}</div>
    <div class="panel-body"><p class="text-muted analytics-week-range"></p><p class="analytics-hours h3"></p><p class="text-muted analytics-hours-scope"></p></div>
</div>
<div data-analytics-panel="lastDate" class="panel panel-default" role="tabpanel" style="display:none">
    <div class="panel-heading">{{translate 'Last Shift Date' scope='SmAnalytics'}}</div>
    <div class="panel-body"><p class="analytics-last-date h3"></p><p class="text-muted">{{translate 'lastShiftScope' category='messages' scope='SmAnalytics'}}</p></div>
</div>
<div data-analytics-panel="lastCheck" class="panel panel-default" role="tabpanel" style="display:none">
    <div class="panel-heading">{{translate 'Last Shift Check' scope='SmAnalytics'}}</div>
    <div class="panel-body"><p class="analytics-last-check h3"></p><p class="text-muted">{{translate 'lastShiftScope' category='messages' scope='SmAnalytics'}}</p></div>
</div>
<div data-analytics-panel="earnings" class="panel panel-default" role="tabpanel" style="display:none">
    <div class="panel-heading">{{translate 'Weekly Earnings' scope='SmAnalytics'}}</div>
    <div class="panel-body"><p class="text-muted analytics-week-range"></p><div class="analytics-weekly-earnings h3"></div><p class="text-muted">{{translate 'earningsScope' category='messages' scope='SmAnalytics'}}</p></div>
</div>
