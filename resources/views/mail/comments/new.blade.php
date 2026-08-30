<x-mail::message>
# New comment awaiting review

**{{ $comment->author_name }}** commented on *{{ $post?->title }}*:

<x-mail::panel>
{{ $comment->body }}
</x-mail::panel>

It is held as **pending** and will not appear on the site until you approve it.

<x-mail::button :url="$moderationUrl">
Review comment
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
