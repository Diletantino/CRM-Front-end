<div class="text-center" style="padding: 16px 0;">
    {{#if showFinish}}
    <button type="button" class="btn btn-danger btn-lg action" data-action="finishShift">
        <span class="fas fa-stop"></span> {{translate 'Finish Shift' scope='SmShift'}}
    </button>
    {{/if}}
    {{#if showComplete}}
    <button type="button" class="btn btn-success btn-lg action" data-action="save">
        <span class="fas fa-check"></span> {{translate 'Complete Calculation' scope='SmShift'}}
    </button>
    {{/if}}
    {{#if showRevision}}
    <button type="button" class="btn btn-primary btn-lg action" data-action="save">
        <span class="fas fa-floppy-disk"></span> {{translate 'Save Changes' scope='SmShift'}}
    </button>
    {{/if}}
</div>
