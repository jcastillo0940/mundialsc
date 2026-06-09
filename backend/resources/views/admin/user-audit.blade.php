@extends('admin.layout')

@section('content')
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="{{ route('admin.users.edit', $user) }}" style="padding:7px 14px;border-radius:10px;background:#20313a;border:1px solid var(--line);font-size:13px">&larr; Volver a edicion</a>
    <h1 style="margin:0">Auditoria del participante</h1>
    <span class="pill">{{ $user->name }}</span>
    <span class="pill">Cedula: {{ $user->cedula }}</span>
</div>

<div class="card">
    <h2>Trazabilidad completa</h2>
    <p class="muted">Esta vista concentra eventos del perfil del participante, sus facturas, flags antifraude y otras acciones relevantes registradas por el sistema o por administradores.</p>

    @if($events->isEmpty())
        <p class="muted">No hay eventos de auditoria para este participante.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Evento</th>
                <th>Actor</th>
                <th>Entidad</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
        @foreach($events as $event)
            <tr>
                <td>{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                <td>
                    <strong>{{ $event->event_type }}</strong><br>
                    <small class="muted">{{ $event->entity_type }}{{ $event->entity_id ? ' #'.$event->entity_id : '' }}</small>
                </td>
                <td>
                    <span class="pill">{{ $event->actor_role ?: 'sistema' }}</span><br>
                    <small class="muted">User ID: {{ $event->user_id ?: '-' }}</small>
                </td>
                <td>
                    <span class="pill">{{ $event->entity_type }}</span>
                </td>
                <td>
                    @if(($event->payload['changes'] ?? []) !== [])
                        <div style="display:grid;gap:6px">
                            @foreach($event->payload['changes'] as $field => $change)
                                <div>
                                    <strong>{{ $field }}</strong><br>
                                    <small class="muted">Antes: {{ is_array($change['before'] ?? null) ? json_encode($change['before']) : ($change['before'] ?? '-') }}</small><br>
                                    <small class="muted">Despues: {{ is_array($change['after'] ?? null) ? json_encode($change['after']) : ($change['after'] ?? '-') }}</small>
                                </div>
                            @endforeach
                        </div>
                    @elseif(! empty($event->payload))
                        <pre style="white-space:pre-wrap;background:#0f171b;padding:8px;border-radius:8px;max-width:520px">{{ json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <span class="muted">Sin detalle adicional</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
        {{ $events->links() }}
    </div>
    @endif
</div>
@endsection
