@extends(config('email-marketing.layout') ?: 'email-marketing::layouts.app')

@section('content')
<div class="campaign-form">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <h1>New Campaign</h1>
        <a href="{{ route('email-marketing.campaigns') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <form action="{{ route('email-marketing.campaigns.store') }}" method="POST" id="campaignForm">
        @csrf

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Campaign Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   placeholder="February 2026 Newsletter"
                                   value="{{ old('name') }}">
                            <small class="text-muted">For internal use only</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Template *</label>
                            <select name="template_id" class="form-select" required id="templateSelect">
                                <option value="">Select a template</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" {{ old('template_id') == $template->id ? 'selected' : '' }}>
                                        {{ $template->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($templates->isEmpty())
                                <small class="text-danger">
                                    No active templates available.
                                    <a href="{{ route('email-marketing.templates.create') }}">Create a template</a>
                                </small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recipients</h5>
                        <span class="badge bg-primary" id="selectedCount">0 selected</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Search Recipients</label>
                            <input type="text" class="form-control" id="hotelSearch"
                                   placeholder="Search by name, email, or address...">
                        </div>

                        <div id="searchResults" class="mb-3" style="display: none;">
                            <div class="list-group" id="searchResultsList"></div>
                        </div>

                        <div class="selected-hotels mb-3">
                            <label class="form-label">Selected Recipients:</label>
                            <div id="selectedHotels">
                                <p class="text-muted small" id="noHotelsSelected">No recipients selected</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <i class="fas fa-paper-plane"></i> Create Campaign
                    </button>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Preview</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Select a template to preview the email.</p>
                        <div id="templatePreview"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="previewBtn" disabled>
                            <i class="fas fa-eye"></i> Show Preview
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Information</h5>
                    </div>
                    <div class="card-body">
                        <ul class="small text-muted mb-0">
                            <li>After creation, the campaign will be in "Draft" status</li>
                            <li>You can start sending from the campaign page</li>
                            <li>Emails are sent via queue</li>
                            <li>Opens are tracked automatically</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Subject:</strong> <span id="previewSubject"></span></p>
                <hr>
                <div id="previewBody" style="border: 1px solid #ddd; padding: 15px; background: #fff;"></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectedHotels = new Map();
    let searchTimeout = null;

    const hotelSearchInput = document.getElementById('hotelSearch');
    const searchResults = document.getElementById('searchResults');
    const searchResultsList = document.getElementById('searchResultsList');
    const selectedHotelsDiv = document.getElementById('selectedHotels');
    const selectedCount = document.getElementById('selectedCount');
    const noHotelsSelected = document.getElementById('noHotelsSelected');
    const submitBtn = document.getElementById('submitBtn');
    const templateSelect = document.getElementById('templateSelect');
    const previewBtn = document.getElementById('previewBtn');

    // Hotel search
    hotelSearchInput.addEventListener('input', function() {
        const query = this.value.trim();

        if (searchTimeout) clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`{{ route('email-marketing.hotels.search') }}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(hotels => {
                    searchResultsList.innerHTML = '';

                    if (hotels.length === 0) {
                        searchResultsList.innerHTML = '<div class="list-group-item text-muted">No results found</div>';
                    } else {
                        hotels.forEach(hotel => {
                            if (!selectedHotels.has(hotel.id)) {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action';
                                item.innerHTML = `
                                    <strong>${hotel.name}</strong>
                                    <small class="text-muted d-block">${hotel.email} - ${hotel.city || 'No city'}</small>
                                `;
                                item.addEventListener('click', () => addHotel(hotel));
                                searchResultsList.appendChild(item);
                            }
                        });
                    }

                    searchResults.style.display = 'block';
                });
        }, 300);
    });

    // Add hotel to selection
    function addHotel(hotel) {
        selectedHotels.set(hotel.id, hotel);
        updateSelectedHotels();
        hotelSearchInput.value = '';
        searchResults.style.display = 'none';
    }

    // Remove hotel from selection
    function removeHotel(hotelId) {
        selectedHotels.delete(hotelId);
        updateSelectedHotels();
    }

    // Update selected hotels display
    function updateSelectedHotels() {
        if (selectedHotels.size === 0) {
            noHotelsSelected.style.display = 'block';
            selectedHotelsDiv.querySelectorAll('.hotel-chip').forEach(el => el.remove());
            submitBtn.disabled = true;
        } else {
            noHotelsSelected.style.display = 'none';
            selectedHotelsDiv.querySelectorAll('.hotel-chip').forEach(el => el.remove());

            selectedHotels.forEach((hotel, id) => {
                const chip = document.createElement('div');
                chip.className = 'hotel-chip badge bg-light text-dark me-2 mb-2 p-2';
                chip.innerHTML = `
                    ${hotel.name}
                    <input type="hidden" name="hotel_ids[]" value="${id}">
                    <button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove"></button>
                `;
                chip.querySelector('.btn-close').addEventListener('click', () => removeHotel(id));
                selectedHotelsDiv.appendChild(chip);
            });

            submitBtn.disabled = !templateSelect.value;
        }

        selectedCount.textContent = `${selectedHotels.size} selected`;
    }

    // Template selection
    templateSelect.addEventListener('change', function() {
        previewBtn.disabled = !this.value;
        submitBtn.disabled = !this.value || selectedHotels.size === 0;
    });

    // Preview
    previewBtn.addEventListener('click', function() {
        const templateId = templateSelect.value;
        if (!templateId) return;

        fetch(`{{ url(config('email-marketing.route_prefix', 'admin/email-marketing')) }}/templates/${templateId}/preview`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('previewSubject').textContent = data.subject;
                document.getElementById('previewBody').innerHTML = data.html;
                new bootstrap.Modal(document.getElementById('previewModal')).show();
            });
    });

    // Close search results on click outside
    document.addEventListener('click', function(e) {
        if (!hotelSearchInput.contains(e.target) && !searchResultsList.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
});
</script>

<style>
.hotel-chip {
    display: inline-flex;
    align-items: center;
    font-size: 0.9rem;
}
.hotel-chip .btn-close {
    font-size: 0.6rem;
}
#searchResultsList {
    max-height: 300px;
    overflow-y: auto;
}
</style>
@endsection
