<div class="page-header"><h3>{{userName}}</h3></div>
{{#if avatarUrl}}
<img src="{{avatarUrl}}" class="img-responsive" style="max-width:160px;border-radius:50%" alt="{{userName}}">
{{else}}
<span role="img" aria-label="{{userName}}" style="display:inline-flex;align-items:center;justify-content:center;width:96px;height:96px;border-radius:50%;background:#3977b6;color:#fff;font-size:32px">{{avatarLabel}}</span>
{{/if}}
