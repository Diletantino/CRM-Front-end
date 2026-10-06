{{#if hasRows}}
<div class="table-responsive">
    <table class="table table-bordered table-condensed">
        <thead>
            <tr>
                <th>{{translate 'site' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteAccess' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteState' category='fields' scope='SmShift'}}</th>
                {{#if showFinancial}}
                <th>{{translate 'siteEarning' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteOfflineBonus' category='fields' scope='SmShift'}}</th>
                {{/if}}
                {{#if canManageState}}<th>{{translate 'Actions'}}</th>{{/if}}
            </tr>
        </thead>
        <tbody>
        {{#each rows}}
            <tr>
                <td><strong>{{site}}</strong></td>
                <td><code>{{site}} - {{login}} - {{password}}</code></td>
                <td><span class="label label-default">{{stateLabel}}</span></td>
                {{#if ../showFinancial}}
                <td>{{earning}}</td>
                <td>{{offlineBonus}}</td>
                {{/if}}
                {{#if ../canManageState}}
                <td class="nowrap">
                    <button type="button" class="btn btn-xs btn-success" data-action="set-site-state" data-index="{{index}}" data-state="Started" {{#if started}}disabled{{/if}}>
                        {{translate 'Start Site' category='labels' scope='SmShift'}}
                    </button>
                    <button type="button" class="btn btn-xs btn-danger" data-action="set-site-state" data-index="{{index}}" data-state="Banned" {{#if banned}}disabled{{/if}}>
                        {{translate 'Banned' category='labels' scope='SmShift'}}
                    </button>
                </td>
                {{/if}}
            </tr>
        {{/each}}
        </tbody>
    </table>
</div>
{{else}}
<span class="text-muted">{{translate 'None'}}</span>
{{/if}}
