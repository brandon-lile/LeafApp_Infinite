<?php
    /** @var App\Models\Player $player */
?>
@if ($player->bans)
    @foreach ($player->bans as $ban)
        <div class="notification {{ $ban->is_expired ? 'is-warning' : 'is-danger' }}">
            {{ $ban->message }}
            @if (! $ban->is_expired)
                <br /><br />
                <p class="is-size-7">
                    Days remaining: {{ $ban->days_remaining }}, for '{{ $ban->type }}', on scope: '{{ $ban->scope }}'
                </p>
            @endif
        </div>
    @endforeach
@endif
