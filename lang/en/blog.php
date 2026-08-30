<?php

/**
 * User-facing strings for the blog.
 *
 * why a lang file even though the site is English-only: strings live in one
 * place instead of being scattered through Blade templates, and adding a
 * second language later means copying this file to lang/ur/blog.php - no
 * template changes at all. Views read them with __('blog.read_more').
 */
return [

    // Navigation and general
    'read_more' => 'Read article',
    'all_posts' => 'All posts',
    'home' => 'Home',
    'search_placeholder' => 'Search articles…',
    'search_hint' => 'Type at least two characters',
    'no_results' => 'Nothing matched ":term".',
    'minutes_read' => '{1} :count min read|[2,*] :count min read',
    'published_on' => 'Published :date',
    'written_by' => 'by :name',
    'related_posts' => 'Related reading',
    'browse_by_category' => 'Categories',
    'popular_tags' => 'Tags',
    'views' => '{0} No views yet|{1} :count view|[2,*] :count views',

    // Empty states
    'no_posts_yet' => 'No articles have been published yet.',
    'no_posts_in_category' => 'No articles in this category yet.',
    'no_posts_with_tag' => 'No articles carry this tag yet.',

    // Comments
    'comments' => '{0} No comments yet|{1} One comment|[2,*] :count comments',
    'leave_comment' => 'Join the conversation',
    'reply' => 'Reply',
    'reply_to' => 'Replying to :name',
    'cancel_reply' => 'Cancel reply',
    'comment_submitted' => 'Thank you! Your comment is awaiting moderation.',
    'comment_rejected' => 'Your comment could not be accepted. Please try again.',
    'comment_too_fast' => 'That was submitted a little too quickly — please try once more.',
    'comment_rate_limited' => 'You are commenting very quickly. Please wait a minute and try again.',
    'comment_awaiting' => 'Awaiting moderation',
    'be_first_to_comment' => 'Be the first to comment.',

    // Admin flash messages
    'post_created' => 'Post created.',
    'post_updated' => 'Post updated.',
    'post_deleted' => 'Post moved to trash.',
    'post_restored' => 'Post restored.',
    'category_created' => 'Category created.',
    'category_updated' => 'Category updated.',
    'category_deleted' => 'Category deleted.',
    'tag_created' => 'Tag created.',
    'tag_updated' => 'Tag updated.',
    'tag_deleted' => 'Tag deleted.',
    'comment_approved' => 'Comment approved and now visible.',
    'comment_rejected_admin' => 'Comment rejected and hidden.',
    'comment_deleted' => 'Comment deleted.',

    // Draft preview
    'draft_preview' => 'Draft preview — this is not visible to the public.',
];
