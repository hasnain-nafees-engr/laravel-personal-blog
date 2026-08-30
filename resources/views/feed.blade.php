<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ config('app.name') }}</title>
        <link>{{ route('home') }}</link>
        <description>Notes on engineering, Laravel and building things that last.</description>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        <lastBuildDate>{{ $posts->first()?->published_at?->toRfc2822String() ?? now()->toRfc2822String() }}</lastBuildDate>
        <atom:link href="{{ route('feed') }}" rel="self" type="application/rss+xml" />
@foreach ($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('posts.show', $post) }}</link>
            <guid isPermaLink="true">{{ route('posts.show', $post) }}</guid>
            <pubDate>{{ $post->published_at?->toRfc2822String() }}</pubDate>
            <author>noreply@{{ request()->getHost() }} ({{ $post->user->name }})</author>
            {{-- CDATA so an ampersand or quote in the summary cannot break the XML. --}}
            <description><![CDATA[{{ $post->summary }}]]></description>
        </item>
@endforeach
    </channel>
</rss>
