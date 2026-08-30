<x-admin-layout :title="'Edit: '.$tag->name">
    @include('admin.tags.form', [
        'action' => route('admin.tags.update', $tag),
        'method' => 'PUT',
    ])
</x-admin-layout>
