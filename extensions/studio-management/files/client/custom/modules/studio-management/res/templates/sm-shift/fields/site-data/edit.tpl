<div class="table-responsive">
    <table class="table table-bordered table-condensed">
        <thead>
            <tr>
                <th>{{translate 'site' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteLogin' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'sitePassword' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteState' category='fields' scope='SmShift'}}</th>
                {{#if showFinancial}}
                <th>{{translate 'siteEarning' category='fields' scope='SmShift'}}</th>
                <th>{{translate 'siteOfflineBonus' category='fields' scope='SmShift'}}</th>
                {{/if}}
                {{#if canAddRemove}}<th></th>{{/if}}
            </tr>
        </thead>
        <tbody>
        {{#each rows}}
            <tr data-index="{{index}}">
                <td>
                    {{#if ../canEditCredentials}}<input class="form-control input-sm" data-field="site" value="{{site}}">{{else}}{{site}}{{/if}}
                </td>
                <td>
                    {{#if ../canEditCredentials}}<input class="form-control input-sm" data-field="login" value="{{login}}">{{else}}{{login}}{{/if}}
                </td>
                <td>
                    {{#if ../canEditCredentials}}<input type="password" class="form-control input-sm" data-field="password" value="{{password}}" autocomplete="new-password">{{else}}<code>{{password}}</code>{{/if}}
                </td>
                <td>{{stateLabel}}</td>
                {{#if ../showFinancial}}
                <td>
                    {{#if ../canEditFinancial}}<input class="form-control input-sm" inputmode="decimal" data-field="earning" value="{{earning}}">{{else}}{{earning}}{{/if}}
                </td>
                <td>
                    {{#if ../canEditFinancial}}<input class="form-control input-sm" inputmode="decimal" data-field="offlineBonus" value="{{offlineBonus}}">{{else}}{{offlineBonus}}{{/if}}
                </td>
                {{/if}}
                {{#if ../canAddRemove}}
                <td><button type="button" class="btn btn-xs btn-danger" data-action="remove-site-row" data-index="{{index}}"><span class="fas fa-times"></span></button></td>
                {{/if}}
            </tr>
        {{/each}}
        </tbody>
    </table>
</div>
{{#if canAddRemove}}
<button type="button" class="btn btn-default btn-sm" data-action="add-site-row">
    <span class="fas fa-plus"></span> {{translate 'Add Site' category='labels' scope='SmShift'}}
</button>
{{/if}}
