<div class="container content">
    <div class="block-center">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4 class="panel-title">{{translate 'Registration' scope='SmRegistrationLink'}}</h4>
            </div>
            <div class="panel-body">
                {{#if notFound}}
                    <p class="text-danger">{{translate 'invitationUnavailable' category='messages' scope='SmRegistrationLink'}}</p>
                {{else}}
                    <p class="text-muted">
                        {{translate 'Account Type' scope='SmRegistrationLink'}}: <strong>{{accountType}}</strong><br>
                        {{translate 'Target Role' scope='SmRegistrationLink'}}: <strong>{{roleName}}</strong><br>
                        {{translate 'Expires At' scope='SmRegistrationLink'}}: <strong>{{expiresAt}}</strong>
                    </p>
                    <div class="registration-form">
                        <div class="form-group">
                            <label>{{translate 'Full Name' scope='SmRegistrationLink'}}</label>
                            <input class="form-control" type="text" name="fullName" maxlength="150" autocomplete="name">
                        </div>
                        <div class="form-group">
                            <label>{{translate 'Email'}}</label>
                            <input class="form-control" type="email" name="email" maxlength="254" autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label>{{translate 'Password'}}</label>
                            <input class="form-control" type="password" name="password" maxlength="255" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label>{{translate 'Confirm Password' scope='SmRegistrationLink'}}</label>
                            <input class="form-control" type="password" name="passwordConfirm" maxlength="255" autocomplete="new-password">
                        </div>
                        <button type="button" class="btn btn-primary" data-action="register">
                            {{translate 'Register' scope='SmRegistrationLink'}}
                        </button>
                    </div>
                    <p class="registration-message" style="margin-top: 15px"></p>
                    <p class="login-link hidden"><a href="./">{{translate 'Go to Login' scope='SmRegistrationLink'}}</a></p>
                {{/if}}
            </div>
        </div>
    </div>
</div>
