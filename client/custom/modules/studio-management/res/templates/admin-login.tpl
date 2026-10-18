<div class="container content">
    <div class="container-centering">
        <div id="login" class="panel panel-default block-center-sm">
            <div class="panel-heading">
                <div class="logo-container">
                    <img src="{{logoSrc}}" class="logo">
                </div>
            </div>
            <div class="panel-body">
                {{#if adminLoginAllowed}}
                    <form id="login-form">
                        <div class="form-group cell" data-name="username">
                            <label for="field-userName">{{translate 'Username'}}</label>
                            <input type="text" name="username" id="field-userName" class="form-control" autocapitalize="off" spellcheck="false" tabindex="1" autocomplete="username" maxlength="255">
                        </div>
                        <div class="form-group cell" data-name="password">
                            <label for="field-password">{{translate 'Password'}}</label>
                            <div data-role="password-input-container">
                                <input type="password" name="password" id="field-password" class="form-control" tabindex="2" autocomplete="current-password" maxlength="255">
                                <a role="button" data-action="toggleShowPassword" class="text-soft" title="{{translate 'View'}}"><span class="far fa-eye"></span></a>
                            </div>
                        </div>
                        <div class="margin-top-2x cell" data-name="submit">
                            <button type="submit" class="btn btn-primary btn-s-wide" id="btn-login" tabindex="3">{{logInText}}</button>
                        </div>
                    </form>
                {{else}}
                    <p class="text-danger">{{translate 'personalLinkRequired' category='messages' scope='SmRegistrationLink'}}</p>
                {{/if}}
            </div>
        </div>
    </div>
</div>

<footer>{{{footer}}}</footer>
