<div class="page-header"><h3>{{translate 'Accounts to complete' scope='SmAccountOnboarding'}}</h3></div>
<p class="text-danger" role="alert" data-feedback></p>
{{#if profile}}
    <div class="panel panel-default">
        <div class="panel-heading">
            <button type="button" class="btn btn-default btn-sm" data-onboarding-back>{{translate 'Back to queue' scope='SmAccountOnboarding'}}</button>
            <strong>{{profile.name}}</strong>
        </div>
        <div class="panel-body">
            <p>{{profile.userName}} · {{profile.typeLabel}}</p>
            <p class="text-muted">{{translate 'Pending completion' scope='SmAccountOnboarding'}}</p>
            <form>
                <div class="row">
                    {{#each fields}}
                    <div class="col-sm-6 form-group">
                        <label for="onboarding-{{name}}">{{label}}</label>
                        <input class="form-control" id="onboarding-{{name}}" type="{{type}}" name="{{name}}" value="{{value}}"
                            {{#if numeric}}min="0" max="100" step="0.0001"{{/if}}
                            {{#ifEqual type 'text'}}maxlength="255"{{/ifEqual}}>
                    </div>
                    {{/each}}
                </div>
                <div class="form-group">
                    <label for="onboarding-bio">{{translate 'smProfileBio' category='fields' scope='User'}}</label>
                    <textarea class="form-control" id="onboarding-bio" name="smProfileBio" rows="4" maxlength="10000">{{profile.smProfileBio}}</textarea>
                </div>
                <div class="form-group">
                    <label for="onboarding-photo">{{translate 'Profile Photos' scope='User'}}</label>
                    <input id="onboarding-photo" name="profilePhoto" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="help-block">{{translate 'photoFormats' category='messages' scope='SmAccountOnboarding'}}</p>
                    {{#each profile.photos}}
                    <div class="form-group">
                        <button type="button" class="btn btn-default btn-sm" data-onboarding-preview="{{id}}">{{name}}</button>
                        <button type="button" class="btn btn-default btn-sm" data-onboarding-remove="{{id}}">{{translate 'Remove photo' scope='SmAccountOnboarding'}}</button>
                    </div>
                    {{/each}}
                    <img data-photo-preview class="img-responsive" style="display:none;max-height:320px" alt="{{translate 'Profile Photos' scope='User'}}">
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-default">{{translate 'Save profile' scope='SmAccountOnboarding'}}</button>
                    <button type="button" class="btn btn-primary" data-onboarding-complete>{{translate 'Complete account registration' scope='SmAccountOnboarding'}}</button>
                </div>
            </form>
        </div>
    </div>
{{else}}
    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr>
                    <th>{{translate 'Account name' scope='SmAccountOnboarding'}}</th>
                    <th>{{translate 'smAccountType' category='fields' scope='User'}}</th>
                    <th>{{translate 'Received at' scope='SmAccountOnboarding'}}</th>
                    <th></th>
                </tr></thead>
                <tbody>
                    {{#each list}}
                    <tr>
                        <td>{{name}}</td><td>{{typeLabel}}</td><td>{{receivedAt}}</td>
                        <td><button type="button" class="btn btn-default btn-sm" data-onboarding-open="{{id}}">{{translate 'Fill profile' scope='SmAccountOnboarding'}}</button></td>
                    </tr>
                    {{else}}
                    <tr><td colspan="4" class="text-muted">{{translate 'emptyQueue' category='messages' scope='SmAccountOnboarding'}}</td></tr>
                    {{/each}}
                </tbody>
            </table>
        </div>
        {{#if hasPrevious}}<button type="button" class="btn btn-default" data-onboarding-page="-1">{{translate 'Previous page' scope='SmAccountOnboarding'}}</button>{{/if}}
        {{#if hasMore}}<button type="button" class="btn btn-default" data-onboarding-page="1">{{translate 'Next page' scope='SmAccountOnboarding'}}</button>{{/if}}
    </div>
{{/if}}
