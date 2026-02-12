@extends('layouts.app')

@section('title', 'Job Applications Dashboard')

@section('content')
<!-- Stats Cards -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card stat-card total">
            <div class="card-body">
                <h6 class="text-muted">Total</h6>
                <h3>{{ $stats['total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card pending">
            <div class="card-body">
                <h6 class="text-muted">Pendente</h6>
                <h3>{{ $stats['pending'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card processing">
            <div class="card-body">
                <h6 class="text-muted">Processando</h6>
                <h3>{{ $stats['processing'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card completed">
            <div class="card-body">
                <h6 class="text-muted">Concluído</h6>
                <h3>{{ $stats['completed'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card rejected">
            <div class="card-body">
                <h6 class="text-muted">Rejeitado</h6>
                <h3>{{ $stats['rejected'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card stat-card failed">
            <div class="card-body">
                <h6 class="text-muted">Falhou</h6>
                <h3>{{ $stats['failed'] }}</h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('job-applications.index') }}" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Buscar..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processando</option>
                    <option value="classified" {{ request('status') == 'classified' ? 'selected' : '' }}>Classificado</option>
                    <option value="scored" {{ request('status') == 'scored' ? 'selected' : '' }}>Pontuado</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejeitado</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Concluído</option>
                    <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Falhou</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="relevant" class="form-select">
                    <option value="">Relevância</option>
                    <option value="yes" {{ request('relevant') == 'yes' ? 'selected' : '' }}>Relevante</option>
                    <option value="no" {{ request('relevant') == 'no' ? 'selected' : '' }}>Não relevante</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="min_score" class="form-control" placeholder="Score mínimo" value="{{ request('min_score') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
                <a href="{{ route('job-applications.index') }}" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Aplicações ({{ $applications->total() }})</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Vaga</th>
                        <th>Candidato</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Criado em</th>
                        <th>Tempo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                    <tr>
                        <td><code>{{ Str::limit($app->job_id, 12) }}</code></td>
                        <td>
                            <strong>{{ $app->job_title ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $app->job_company ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <strong>{{ $app->candidate_name ?? 'N/A' }}</strong><br>
                            <small class="text-muted">{{ $app->candidate_email ?? 'N/A' }}</small>
                        </td>
                        <td>
                            <span class="badge bg-{{ $app->status_badge }}">
                                {{ $app->status_label }}
                            </span>
                            @if($app->is_relevant === false)
                            <br><small class="text-muted">Não relevante</small>
                            @endif
                        </td>
                        <td>
                            @if($app->match_score)
                            <span class="badge bg-{{ $app->score_badge }} score-badge">
                                {{ $app->match_score }}
                            </span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <small>{{ $app->created_at->format('d/m/Y H:i') }}</small>
                        </td>
                        <td>
                            @if($app->processing_time_seconds)
                            <small>{{ $app->processing_time_seconds }}s</small>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('job-applications.show', $app) }}" class="btn btn-sm btn-info" title="Ver detalhes">
                                <i class="bi bi-eye"></i>
                            </a>

                            @if($app->canReprocess())
                            <form action="{{ route('job-applications.reprocess', $app) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning" title="Reprocessar">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                            </form>
                            @endif

                            @if(!$app->isCompleted())
                            <form action="{{ route('job-applications.mark-completed', $app) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success" title="Marcar completo">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ddd;"></i>
                            <p class="text-muted mt-2">Nenhuma aplicação encontrada</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($applications->hasPages())
    <div class="card-footer">
        {{ $applications->links() }}
    </div>
    @endif
</div>
@endsection