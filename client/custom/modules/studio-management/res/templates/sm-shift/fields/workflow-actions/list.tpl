<div class="btn-group btn-group-xs nowrap">
    {{#if showStart}}
    <a class="btn btn-success" href="#SmShift/edit/{{id}}/start">{{translate 'Start Shift' scope='SmShift'}}</a>
    {{/if}}
    {{#if showFinish}}
    <button type="button" class="btn btn-danger" data-action="finish-shift">{{translate 'Finish Shift' scope='SmShift'}}</button>
    {{/if}}
    {{#if showCalculation}}
    <a class="btn btn-warning" href="#SmShift/edit/{{id}}/count">{{translate 'Complete Calculation' scope='SmShift'}}</a>
    {{/if}}
    {{#if showEdit}}
    <a class="btn btn-default" href="#SmShift/edit/{{id}}{{#if editMode}}/{{editMode}}{{/if}}">{{translate 'Edit'}}</a>
    {{/if}}
    {{#if operatorCompleted}}
    <button type="button" class="btn btn-default" disabled>{{translate 'Calculated' scope='SmShift'}}</button>
    {{/if}}
</div>
