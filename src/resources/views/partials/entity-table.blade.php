<table class="table table-sm">
    <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>ID</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @forelse($entities as $entity)
            <tr>
                <td>
                    {{ $entity->name ?: $entity->entity_id }}
                    @if($entity->notes)
                        <br><small class="text-muted">{{ $entity->notes }}</small>
                    @endif
                </td>
                <td>{{ ucfirst($entity->entity_type) }}</td>
                <td>{{ $entity->entity_id }}</td>
                <td class="text-right">
                    <form action="{{ route('seat-spy-hunter.settings.entities.destroy', $entity) }}" method="POST">
                        {{ csrf_field() }}
                        {{ method_field('DELETE') }}
                        <button type="submit" class="btn btn-link btn-sm text-danger">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-muted">Nothing configured yet.</td>
            </tr>
        @endforelse
    </tbody>
</table>
