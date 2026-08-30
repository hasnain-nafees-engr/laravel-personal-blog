<x-admin-layout title="New post">
    @include('admin.posts.form', [
        'action' => route('admin.posts.store'),
        'method' => 'POST',
    ])
</x-admin-layout>
