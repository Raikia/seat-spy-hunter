@if($category === 'monitored')
    <form action="{{ route('seat-spy-hunter.settings.entities.store') }}" method="POST" class="mb-3 seat-spy-hunter-entity-form">
        {{ csrf_field() }}
        <input type="hidden" name="category" value="monitored">
        <input type="hidden" name="entity_type" value="corporation">
        <input type="hidden" name="name" class="seat-spy-hunter-selected-name">
        <div class="form-group">
            <label>Corporation</label>
            <select name="entity_id" class="form-control seat-spy-hunter-entity-select" data-search-url="{{ route('seat-spy-hunter.settings.search.corporations') }}" data-placeholder="Search corporations..." required style="width: 100%;"></select>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-control">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">
            <i class="fas fa-plus"></i> Add Corporation
        </button>
    </form>

    <form action="{{ route('seat-spy-hunter.settings.entities.store') }}" method="POST" class="mb-3 seat-spy-hunter-entity-form">
        {{ csrf_field() }}
        <input type="hidden" name="category" value="monitored">
        <input type="hidden" name="entity_type" value="alliance">
        <input type="hidden" name="name" class="seat-spy-hunter-selected-name">
        <div class="form-group">
            <label>Alliance</label>
            <select name="entity_id" class="form-control seat-spy-hunter-entity-select" data-search-url="{{ route('seat-spy-hunter.settings.search.alliances') }}" data-placeholder="Search alliances..." required style="width: 100%;"></select>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-control">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">
            <i class="fas fa-plus"></i> Add Alliance
        </button>
    </form>
@else
    <form action="{{ route('seat-spy-hunter.settings.entities.store') }}" method="POST" class="mb-3 seat-spy-hunter-entity-form">
        {{ csrf_field() }}
        <input type="hidden" name="category" value="hostile">
        <input type="hidden" name="name" class="seat-spy-hunter-selected-name">
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Type</label>
                <select name="entity_type" class="form-control seat-spy-hunter-hostile-type">
                    <option value="character">Character</option>
                    <option value="corporation">Corporation</option>
                    <option value="alliance">Alliance</option>
                </select>
            </div>
            <div class="form-group col-md-8">
                <label>Entity</label>
                <select name="entity_id" class="form-control seat-spy-hunter-hostile-entity-select" data-search-url="{{ route('seat-spy-hunter.settings.search.entities') }}" data-placeholder="Search hostile entity..." required style="width: 100%;"></select>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <input type="text" name="notes" class="form-control">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">
            <i class="fas fa-plus"></i> Add Hostile
        </button>
    </form>
@endif
