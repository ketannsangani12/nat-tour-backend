@php
    $d             = resolveWaiverData($hist);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $signers       = $d['signers'];
    $signerCount   = $d['signerCount'];
@endphp

<div class="log-entry" data-type="system">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user">
            <img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info">
                <span class="log-user-name">{{ $createdByName }}</span>
                @if ($createdByRole)
                    <span class="log-cred">{{ $createdByRole }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="log-badge waiver">Waiver</div>

    <div class="log-content">
        <div class="sys-card waiver">
            <div class="sys-card-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">
                    Liability Waiver Signed — {{ $signerCount }} {{ Str::plural('person', $signerCount) }}
                </div>
                <div class="sys-card-rows">
                    @if (!empty($signers))
                        <div class="waiver-list">
                            <div class="waiver-list-header">
                                <span>Person</span>
                                <span>Age</span>
                            </div>
                            @foreach ($signers as $signer)
                                <div class="waiver-list-row">
                                    <span class="waiver-signer-name">{{ $signer['name'] }}</span>
                                    <span class="waiver-signer-age">{{ $signer['age'] !== '' ? $signer['age'] : '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="sys-card-row">Waiver signed</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
