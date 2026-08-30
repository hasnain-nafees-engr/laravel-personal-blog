{{-- Comment thread + form. Included by posts/show.blade.php. --}}
<section id="comments" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-10 sm:px-6"
         aria-labelledby="comments-heading">

    <h2 id="comments-heading" class="font-serif text-2xl font-semibold text-ink-900 dark:text-paper-50">
        {{ trans_choice('blog.comments', $post->approvedComments->count(), ['count' => $post->approvedComments->count()]) }}
    </h2>

    @if ($post->approvedComments->isEmpty())
        <p class="mt-4 text-sm text-ink-400 dark:text-ink-300">{{ __('blog.be_first_to_comment') }}</p>
    @else
        <ol class="mt-6 space-y-6">
            @foreach ($post->approvedComments as $comment)
                <li>
                    @include('posts.partials.comment', ['comment' => $comment, 'isReply' => false])

                    @if ($comment->approvedReplies->isNotEmpty())
                        <ol class="mt-4 space-y-4 border-l-2 border-paper-200 pl-5 dark:border-ink-800">
                            @foreach ($comment->approvedReplies as $reply)
                                <li>
                                    @include('posts.partials.comment', ['comment' => $reply, 'isReply' => true])
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    {{-- Comment form --}}
    <div x-data="{ replyTo: null, replyName: '' }" class="mt-10">
        <h3 class="font-serif text-xl font-semibold text-ink-900 dark:text-paper-50">
            {{ __('blog.leave_comment') }}
        </h3>

        <template x-if="replyTo">
            <p class="mt-2 flex items-center gap-2 text-sm text-ink-500 dark:text-paper-300">
                <span x-text="`{{ __('blog.reply_to', ['name' => '__NAME__']) }}`.replace('__NAME__', replyName)"></span>
                <button type="button" @click="replyTo = null; replyName = ''"
                        class="text-ochre-700 underline dark:text-ochre-300">
                    {{ __('blog.cancel_reply') }}
                </button>
            </p>
        </template>

        <form method="POST" action="{{ route('comments.store', $post) }}"
              class="mt-4 space-y-4" novalidate>
            {{-- @csrf writes the hidden token every POST form needs. Without
                 it Laravel rejects the request with a 419 - that is CSRF
                 protection (PreventRequestForgery middleware) doing its job. --}}
            @csrf

            <input type="hidden" name="parent_id" :value="replyTo">

            {{-- Honeypot part 1: hidden from humans, irresistible to bots. --}}
            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="website">Website (leave this empty)</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            {{-- Honeypot part 2: encrypted render timestamp - a post that
                 arrives within a few seconds was not typed by a person. --}}
            <input type="hidden" name="started_at" value="{{ Crypt::encryptString((string) now()->timestamp) }}">

            @guest
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="author_name" class="block text-sm font-medium">Name</label>
                        <input type="text" id="author_name" name="author_name"
                               value="{{ old('author_name') }}" required maxlength="80"
                               @error('author_name') aria-invalid="true" aria-describedby="author_name-error" @enderror
                               class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                                      focus:border-ochre-500 focus:ring-ochre-500
                                      dark:border-ink-700 dark:bg-ink-900">
                        @error('author_name')
                            <p id="author_name-error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="author_email" class="block text-sm font-medium">Email</label>
                        <input type="email" id="author_email" name="author_email"
                               value="{{ old('author_email') }}" required maxlength="180"
                               @error('author_email') aria-invalid="true" aria-describedby="author_email-error" @enderror
                               class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                                      focus:border-ochre-500 focus:ring-ochre-500
                                      dark:border-ink-700 dark:bg-ink-900">
                        <p class="mt-1 text-xs text-ink-400">Never published.</p>
                        @error('author_email')
                            <p id="author_email-error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endguest

            <div>
                <label for="body" class="block text-sm font-medium">Comment</label>
                <textarea id="body" name="body" rows="4" required minlength="3" maxlength="2000"
                          @error('body') aria-invalid="true" aria-describedby="body-error" @enderror
                          class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                                 focus:border-ochre-500 focus:ring-ochre-500
                                 dark:border-ink-700 dark:bg-ink-900">{{ old('body') }}</textarea>
                @error('body')
                    <p id="body-error" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-medium text-paper-50
                               transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
                    Post comment
                </button>
                <p class="text-xs text-ink-400">Comments are reviewed before they appear.</p>
            </div>
        </form>
    </div>
</section>
