{{#if profileLinkAllowed}}
    {{#if url}}<a href="{{url}}">{{nameValue}}</a>{{else}}<span>{{nameValue}}</span>{{/if}}
{{else}}
    <span>{{nameValue}}</span>
{{/if}}
