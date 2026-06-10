@extends('admin.layout')

@section('content')
<h1>Campañas Push</h1>

<div class="card">
    <h2>Nueva campaña</h2>
    <form method="post" action="{{ route('admin.push-campaigns.store') }}" class="grid">
        @csrf
        <div class="row">
            <input name="title" placeholder="Título" required>
            <input name="button_text" placeholder="Texto del botón">
        </div>
        <textarea name="description" placeholder="Descripción"></textarea>
        <div class="row">
            <input name="image_url" placeholder="URL de imagen">
            <input name="button_url" placeholder="URL del botón">
        </div>
        <div class="row">
            <select name="audience_type" id="push-audience-type-new" onchange="window.__syncPushAudience && window.__syncPushAudience('new')" >
                <option value="all">Todos los usuarios</option>
                <option value="user">Usuario específico</option>
                <option value="branch">Sucursal</option>
                <option value="active">Usuarios activos</option>
            </select>
            <select name="target_user_id" data-push-user>
                <option value="">-- Selecciona usuario --</option>
                @foreach($clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }} - {{ $client->email }} @if(! $client->is_active) (inactivo) @endif</option>
                @endforeach
            </select>
            <select name="target_branch_id" data-push-branch>
                <option value="">-- Selecciona sucursal --</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
            <input name="send_at" type="datetime-local" placeholder="Programar envío">
        </div>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="only_active_users" value="1" style="width:auto">
            <span>Limitar solo a usuarios activos</span>
        </label>
        <div class="row">
            <button type="submit" name="send_now" value="1">Guardar y enviar ahora</button>
            <button type="submit" class="ghost">Guardar borrador</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Historial</h2>
    <table>
        <thead>
        <tr>
            <th>Título</th>
            <th>Audiencia</th>
            <th>Programada</th>
            <th>Estado</th>
            <th>Envío</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        @forelse($campaigns as $campaign)
            <tr>
                <td>
                    <strong>{{ $campaign->push_title ?: $campaign->name }}</strong><br>
                    <small class="muted">{{ $campaign->push_description ?: $campaign->description }}</small>
                </td>
                <td>
                    {{ $campaign->push_audience_type === 'user' ? 'Usuario específico' : ($campaign->push_audience_type === 'branch' ? 'Sucursal' : ($campaign->push_audience_type === 'active' ? 'Usuarios activos' : 'Todos')) }}
                    @if($campaign->push_audience_type === 'user' && $campaign->targetUser)
                        <br><small class="muted">{{ $campaign->targetUser->name }}</small>
                    @endif
                </td>
                <td>{{ optional($campaign->push_send_at)->format('d/m/Y H:i') ?: '-' }}</td>
                <td><span class="pill">{{ $campaign->push_status ?? 'draft' }}</span></td>
                <td>{{ $campaign->push_sent_count ?? 0 }} enviados / {{ $campaign->push_failed_count ?? 0 }} fallidos</td>
                <td>
                    <div class="row" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
                        <form method="post" action="{{ route('admin.push-campaigns.test', $campaign) }}">
                            @csrf
                            <button type="submit" class="ghost">Probar</button>
                        </form>
                        <form method="post" action="{{ route('admin.push-campaigns.send', $campaign) }}">
                            @csrf
                            <button type="submit">Enviar</button>
                        </form>
                    </div>
                    <form method="post" action="{{ route('admin.push-campaigns.update', $campaign) }}" class="grid" style="margin-top:10px">
                        @csrf
                        @method('put')
                        <input name="title" value="{{ $campaign->push_title ?: $campaign->name }}" placeholder="Título">
                        <textarea name="description" placeholder="Descripción">{{ $campaign->push_description ?: $campaign->description }}</textarea>
                        <div class="row">
                            <input name="image_url" value="{{ $campaign->push_image_url }}" placeholder="URL de imagen">
                            <input name="button_text" value="{{ $campaign->push_button_text }}" placeholder="Texto del botón">
                            <input name="button_url" value="{{ $campaign->push_button_url }}" placeholder="URL del botón">
                        </div>
                        <div class="row">
                            <select name="audience_type" data-push-audience value="{{ $campaign->push_audience_type }}">
                                <option value="all" @selected($campaign->push_audience_type === 'all')>Todos</option>
                                <option value="user" @selected($campaign->push_audience_type === 'user')>Usuario específico</option>
                                <option value="branch" @selected($campaign->push_audience_type === 'branch')>Sucursal</option>
                                <option value="active" @selected($campaign->push_audience_type === 'active')>Usuarios activos</option>
                            </select>
                            <select name="target_user_id" data-push-user>
                                <option value="">-- Usuario --</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" @selected((int) $campaign->push_target_user_id === (int) $client->id)>{{ $client->name }} - {{ $client->email }}</option>
                                @endforeach
                            </select>
                            <select name="target_branch_id" data-push-branch>
                                <option value="">-- Sucursal --</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((int) $campaign->push_target_branch_id === (int) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            <input name="send_at" type="datetime-local" value="{{ optional($campaign->push_send_at)->format('Y-m-d\TH:i') }}">
                        </div>
                        <label style="display:flex;align-items:center;gap:8px">
                            <input type="checkbox" name="only_active_users" value="1" @checked($campaign->push_only_active_users) style="width:auto">
                            <span>Solo usuarios activos</span>
                        </label>
                        <div class="row">
                            <select name="push_status">
                                <option value="draft" @selected($campaign->push_status === 'draft')>Draft</option>
                                <option value="scheduled" @selected($campaign->push_status === 'scheduled')>Scheduled</option>
                                <option value="sending" @selected($campaign->push_status === 'sending')>Sending</option>
                                <option value="sent" @selected($campaign->push_status === 'sent')>Sent</option>
                                <option value="failed" @selected($campaign->push_status === 'failed')>Failed</option>
                            </select>
                            <button type="submit" class="ghost">Guardar cambios</button>
                        </div>
                    </form>
                    <form method="post" action="{{ route('admin.push-campaigns.destroy', $campaign) }}" style="margin-top:8px">
                        @csrf
                        @method('delete')
                        <button type="submit" class="danger">Eliminar</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No hay campañas registradas.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>
    (() => {
        const syncForm = (form) => {
            if (!form) return;
            const audience = form.querySelector('[name="audience_type"]');
            const user = form.querySelector('[data-push-user]');
            const branch = form.querySelector('[data-push-branch]');
            const active = form.querySelector('[name="only_active_users"]');

            const apply = () => {
                const value = audience?.value;
                if (user) user.style.display = value === 'user' ? '' : 'none';
                if (branch) branch.style.display = value === 'branch' ? '' : 'none';
                if (active?.closest('label')) {
                    active.closest('label').style.display = value === 'active' ? 'none' : 'flex';
                }
            };

            audience?.addEventListener('change', apply);
            apply();
        };

        document.querySelectorAll('form').forEach(syncForm);
        window.__syncPushAudience = () => document.querySelectorAll('form').forEach(syncForm);
    })();
</script>
@endsection
