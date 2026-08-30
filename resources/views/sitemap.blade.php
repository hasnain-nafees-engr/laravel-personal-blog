<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('posts.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
@foreach ($posts as $post)
    <url>
        <loc>{{ route('posts.show', $post) }}</loc>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
@foreach ($categories as $category)
    <url>
        <loc>{{ route('categories.show', $category) }}</loc>
        <lastmod>{{ $category->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
@endforeach
@foreach ($tags as $tag)
    <url>
        <loc>{{ route('tags.show', $tag) }}</loc>
        <lastmod>{{ $tag->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.4</priority>
    </url>
@endforeach
</urlset>
