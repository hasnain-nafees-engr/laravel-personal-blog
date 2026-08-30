<x-admin-layout title="New category">
    @include('admin.categories.form', [
        'action' => route('admin.categories.store'),
        'method' => 'POST',
    ])
</x-admin-layout>
