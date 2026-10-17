<div class="text-center" style="padding: 16px 0;">
    {{#if showStart}}
    <button type="button" class="btn btn-success btn-lg" data-action="workflowSave">
        <span class="fas fa-play"></span> {{translate 'Start Shift' scope='SmShift'}}
    </button>
    {{/if}}
    {{#if showFinish}}
    <button type="button" class="btn btn-danger btn-lg" data-action="workflowFinish">
        <span class="fas fa-stop"></span> {{translate 'Finish Shift' scope='SmShift'}}
    </button>
    {{/if}}
    {{#if showComplete}}
    <button type="button" class="btn btn-success btn-lg" data-action="workflowSave">
        <span class="fas fa-check"></span> {{translate 'Complete Calculation' scope='SmShift'}}
    </button>
    {{/if}}
    {{#if showRevision}}
    <button type="button" class="btn btn-primary btn-lg" data-action="workflowSave">
        <span class="fas fa-floppy-disk"></span> {{translate 'Save Changes' scope='SmShift'}}
    </button>
    {{/if}}
</div>
