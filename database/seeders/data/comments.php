<?php

/**
 * Realistic comment threads for the demo blog.
 *
 * Keyed by the article slug they belong to. Each entry may carry `replies`,
 * which exercises the self-referencing parent_id relationship, and a `status`
 * so the moderation queue has something in it.
 */

return [

    'why-your-laravel-app-is-slow-the-n-1-problem' => [
        [
            'author' => 'Priya Raman',
            'email' => 'priya@example.com',
            'body' => 'The preventLazyLoading tip alone was worth the read. I had it switched on in a side project and could never remember why it only fired sometimes — the "more than one row" detail explains it completely.',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'That caught me out too. It is in Builder::hydrate() if you want to see it: the guard is only armed when the result set has more than one row, because a single model reading a relation is one extra query rather than an N+1.',
                    'by_admin' => true,
                ],
            ],
        ],
        [
            'author' => 'Tom Whitfield',
            'email' => 'tom@example.com',
            'body' => 'Do you ever use lazy() or chunk() for the bigger loops, or is with() enough in practice? We have a nightly export that walks about 80k rows and I am never sure which problem I am solving.',
        ],
    ],

    'route-model-binding-by-slug-and-the-trap-nobody-mentions' => [
        [
            'author' => 'Marcus Lee',
            'email' => 'marcus@example.com',
            'body' => '404 over 403 for unpublished content is such an easy thing to get wrong. We shipped a 403 on scheduled posts and a competitor found an unannounced feature name in the URL before we launched it. Painful lesson, exactly as you describe.',
        ],
        [
            'author' => 'Aisha Karim',
            'email' => 'aisha@example.com',
            'body' => 'Question on getRouteKeyName vs {post:slug} — does the model version cause you any trouble in the admin panel where you sometimes want to look things up by id?',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'One place: the restore route for soft-deleted posts takes {id} rather than the model, because a trashed post is excluded from binding anyway. Everything else stayed on the slug quite happily.',
                    'by_admin' => true,
                ],
            ],
        ],
    ],

    'form-requests-where-validation-actually-belongs' => [
        [
            'author' => 'Daniel Ortiz',
            'email' => 'daniel@example.com',
            'body' => 'The Rule::unique()->ignore() point deserves to be shouted from a rooftop. I have debugged "slug has already been taken" on an unchanged record more times than I would like to admit.',
        ],
        [
            'author' => 'Ffion Davies',
            'email' => 'ffion@example.com',
            'body' => 'prepareForValidation was the missing piece for me. I had been normalising input in the controller and then wondering why my validation rules were checking the raw value.',
        ],
    ],

    'policies-and-middleware-answer-different-questions' => [
        [
            'author' => 'Sofia Almeida',
            'email' => 'sofia@example.com',
            'body' => 'Genuinely useful to see the mistake written up rather than just the finished rule. "Two mechanisms contradicting each other and the blunter one won" is a very good way to put it.',
        ],
        [
            'author' => 'Kwame Mensah',
            'email' => 'kwame@example.com',
            'body' => 'Why avoid Gate::before for the admin override? We use it and it does remove a lot of isAdmin() calls from our policies.',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'Mostly readability. With Gate::before, a policy method that deliberately says no to admins never runs, and you cannot see that by reading the policy — you have to know the provider exists. Having each policy ask isAdmin() itself is more typing but the rule is where you look for it. Your call though; it is a reasonable trade either way.',
                    'by_admin' => true,
                ],
            ],
        ],
    ],

    'a-production-dockerfile-for-laravel-stage-by-stage' => [
        [
            'author' => 'Rebecca Nolan',
            'email' => 'rebecca@example.com',
            'body' => 'The UID build argument is the tip I wish I had four years ago. Every new starter on our team lost half a day to root-owned files in storage before someone finally wrote it down.',
        ],
        [
            'author' => 'Igor Petrov',
            'email' => 'igor@example.com',
            'body' => 'Curious why you kept nginx and php-fpm as separate containers rather than something like FrankenPHP or Octane. Was that a deliberate choice?',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'Deliberate, but for a slightly boring reason: nginx plus php-fpm is the setup almost every Laravel deployment still uses, so it is the one worth being able to explain. Octane is genuinely faster and I would reach for it under load — it just brings a different set of things to understand, like state leaking between requests.',
                    'by_admin' => true,
                ],
            ],
        ],
    ],

    'why-i-stopped-testing-on-sqlite' => [
        [
            'author' => 'Elena Vasquez',
            'email' => 'elena@example.com',
            'body' => 'The HAVING difference between MySQL and PostgreSQL got us last year, in the other direction — code written against PostgreSQL that quietly did the wrong thing on MySQL. Same lesson.',
        ],
        [
            'author' => 'Ben Carter',
            'email' => 'ben@example.com',
            'body' => 'How much slower is the suite in practice? We have around 900 tests and the speed is the only reason anyone still defends SQLite here.',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'About two seconds on ~190 tests, so call it a second per hundred. At 900 I would expect single-digit seconds. If it mattered I would look at running the suite in parallel before I went back to a different database engine.',
                    'by_admin' => true,
                ],
            ],
        ],
        [
            'author' => 'crypto_deals_now',
            'email' => 'spam@example.com',
            'body' => 'Great post!!! Check out my site for AMAZING offers on database hosting, limited time only!!!',
            'status' => 'rejected',
        ],
    ],

    'what-actually-happens-when-you-dispatch-a-job' => [
        [
            'author' => 'Yusuf Ahmed',
            'email' => 'yusuf@example.com',
            'body' => 'The queue:restart section explains an entire afternoon I lost last month. I was convinced something was cached and kept clearing caches that had nothing to do with it.',
        ],
        [
            'author' => 'Hannah Brooks',
            'email' => 'hannah@example.com',
            'body' => 'Strong agree on "dispatched" vs "works" being two different claims. We had a job asserted with Queue::fake for a year that would have thrown on the first line if it had ever actually run.',
        ],
    ],

    'your-env-file-lies-to-you-inside-docker' => [
        [
            'author' => 'Lucas Meyer',
            'email' => 'lucas@example.com',
            'body' => 'Losing the dev database to your own test suite is a rite of passage, but the TestCase guard is a genuinely good fix. Stealing it.',
        ],
        [
            'author' => 'Nadia Hussain',
            'email' => 'nadia@example.com',
            'body' => 'Does the same \$_SERVER precedence apply outside Docker, or is it specific to how env_file works?',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => "It is Laravel's reader, not Docker — a real environment variable always beats the .env file. Docker just makes it happen constantly, because env_file turns every line of .env into one. You would see the same thing on a server that exports variables in the systemd unit.",
                    'by_admin' => true,
                ],
            ],
        ],
        [
            'author' => 'Anonymous',
            'email' => 'someone@example.com',
            'body' => 'Is there a reason not to just delete .env inside the container and rely entirely on env_file? Feels like it would remove the ambiguity.',
            'status' => 'pending',
        ],
    ],

    'index-the-query-you-actually-run' => [
        [
            'author' => 'Grace Adeyemi',
            'email' => 'grace@example.com',
            'body' => '"If you cannot name the query, do not add the index" is going straight into our review checklist. We have a table with eleven indexes and I doubt three of them are used.',
        ],
        [
            'author' => 'Sam Whitaker',
            'email' => 'sam@example.com',
            'body' => 'The note about Seq Scan on small tables being correct behaviour is worth repeating. I have watched people add indexes to "fix" a plan on a table with forty rows.',
        ],
    ],

    'caching-is-easy-invalidation-is-the-job' => [
        [
            'author' => 'Marta Kowalski',
            'email' => 'marta@example.com',
            'body' => 'The serializable_classes change is going to catch a lot of people upgrading to 13. Silently returning an incomplete class is about the worst possible failure mode.',
            'replies' => [
                [
                    'author' => 'Hasnain Nafees',
                    'email' => 'admin@example.com',
                    'body' => 'Agreed, though I understand why they defaulted it to off — it closes a real attack. Caching arrays instead of models costs almost nothing and sidesteps it entirely, so that is the route I took.',
                    'by_admin' => true,
                ],
            ],
        ],
        [
            'author' => 'Oliver Grant',
            'email' => 'oliver@example.com',
            'body' => "Putting every key in one class looks like overkill until the second time you fix a stale-cache bug caused by a typo'd string. Been there.",
        ],
    ],

    'how-to-read-a-stack-trace-without-panicking' => [
        [
            'author' => 'Chloe Bennett',
            'email' => 'chloe@example.com',
            'body' => '"Where it surfaced is not where it was caused" — the @foreach shadowing example is such a good illustration. I would have spent an hour in the comments partial.',
        ],
        [
            'author' => 'Ravi Shankar',
            'email' => 'ravi@example.com',
            'body' => "The error-shapes table is a lovely idea. I am going to keep one for our own codebase's recurring failures.",
        ],
    ],

    'what-i-look-for-in-a-code-review' => [
        [
            'author' => 'Julia Fernandez',
            'email' => 'julia@example.com',
            'body' => '"A comment explaining a bad name is a workaround" is going on the wall. Most of our review comments are style nits that Pint would fix in two seconds.',
        ],
        [
            'author' => 'Nathan Cole',
            'email' => 'nathan@example.com',
            'body' => 'Asking rather than instructing has genuinely changed how our reviews go. Also the point about saying what is good — easy to skip, and it is the only way anyone learns which patterns to repeat.',
        ],
        [
            'author' => 'Zoe Lindqvist',
            'email' => 'zoe@example.com',
            'body' => 'Would you add anything about review size? We find anything over ~400 lines gets rubber-stamped no matter how careful the reviewer intends to be.',
            'status' => 'pending',
        ],
    ],

];
