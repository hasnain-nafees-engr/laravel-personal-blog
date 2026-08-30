<x-admin-layout :title="'Edit: '.$category->name">
    @include('admin.categories.form', [
        'action' => route('admin.categories.update', $category),
        'method' => 'PUT',
    ])
</x-admin-layout>
