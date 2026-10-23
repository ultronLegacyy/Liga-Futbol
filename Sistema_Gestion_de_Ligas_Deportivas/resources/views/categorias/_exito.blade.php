@if (session('success'))
    <p role="status" class="mb-4 px-4 py-3 rounded-lg bg-green-500/10 border border-green-500/30 text-green-400 text-sm">
        {{ session('success') }}
    </p>
@endif
