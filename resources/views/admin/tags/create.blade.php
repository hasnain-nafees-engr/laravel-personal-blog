<x-admin-layout title="New tag">
    @include('admin.tags.form', [
        'action' => route('admin.tags.store'),
        'method' => 'POST',
    ])
</x-admin-layout>
