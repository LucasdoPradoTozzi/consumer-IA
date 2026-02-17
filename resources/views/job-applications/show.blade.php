@extends('layouts.app')

@section('title', 'Detalhes da Aplicação - ' . $jobApplication->job_id)

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-file-earmark-text"></i> Detalhes da Aplicação</h2>
            <a href="{{ route('job-applications.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column - Details -->
    <div class="col-md-8">
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header bg-{{ $jobApplication->status_badge }} text-white">
                <h5 class="mb-0">
                    <i class="bi bi-info-circle"></i> Status: {{ $jobApplication->status_label }}
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Job ID:</strong> <code>{{ $jobApplication->job_id }}</code></p>
                        <p><strong>Criado em:</strong> {{ $jobApplication->created_at->format('d/m/Y H:i:s') }}</p>
                        @if($jobApplication->processing_time_seconds)
                        <p><strong>Tempo de processamento:</strong> {{ $jobApplication->processing_time_seconds }}s</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        @if($jobApplication->match_score)
                        <p><strong>Score:</strong>
                            <span class="badge bg-{{ $jobApplication->score_badge }} score-badge">
                                {{ $jobApplication->match_score }}/100
                            </span>
                        </p>
                        @endif
                        @if($jobApplication->is_relevant !== null)
                        <p><strong>Relevante:</strong>
                            @if($jobApplication->is_relevant)
                            <span class="badge bg-success">Sim</span>
                            @else
                            <span class="badge bg-danger">Não</span>
                            @endif
                        </p>
                        @endif
                        @if($jobApplication->email_sent)
                        <p><strong>Email enviado:</strong>
                            <i class="bi bi-check-circle-fill text-success"></i>
                            {{ $jobApplication->email_sent_at?->format('d/m/Y H:i') }}
                        </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Job Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-briefcase"></i> Informações da Vaga</h5>
            </div>
            <div class="card-body">
                <h6><strong>{{ $jobApplication->job_title }}</strong></h6>
                <p class="text-muted">{{ $jobApplication->job_company }}</p>

                @if($jobApplication->job_description)
                <h6 class="mt-3">Descrição:</h6>
                <p style="white-space: pre-wrap;">{{ $jobApplication->job_description }}</p>
                @endif

                @if($jobApplication->job_skills)
                <h6 class="mt-3">Skills Requeridas:</h6>
                <div>
                    @foreach($jobApplication->job_skills as $skill)
                    <span class="badge bg-primary">{{ $skill }}</span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>

        <!-- Candidate Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person"></i> Informações do Candidato</h5>
            </div>
            <div class="card-body">
                <p><strong>Nome:</strong> {{ $jobApplication->candidate_name }}</p>
                <p><strong>Email:</strong> {{ $jobApplication->candidate_email }}</p>

                @if($jobApplication->candidate_data)
                @if(isset($jobApplication->candidate_data['skills']))
                <h6 class="mt-3">Skills do Candidato:</h6>
                <div>
                    @foreach($jobApplication->candidate_data['skills'] as $skill)
                    <span class="badge bg-success">{{ $skill }}</span>
                    @endforeach
                </div>
                @endif

                @if(isset($jobApplication->candidate_data['experience']))
                <h6 class="mt-3">Experiência:</h6>
                <p>{{ $jobApplication->candidate_data['experience'] }}</p>
                @endif
                @endif
            </div>
        </div>

        <!-- Classification -->
        @if($jobApplication->classification_reason)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tags"></i> Classificação</h5>
            </div>
            <div class="card-body">
                <p><strong>Razão:</strong></p>
                <p>{{ $jobApplication->classification_reason }}</p>
            </div>
        </div>
        @endif

        <!-- Score Justification -->
        @if($jobApplication->score_justification)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Justificativa do Score</h5>
            </div>
            <div class="card-body">
                <p>{{ $jobApplication->score_justification }}</p>
            </div>
        </div>
        @endif

        <!-- Cover Letter -->
        @if($jobApplication->cover_letter)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-envelope"></i> Carta de Apresentação</h5>
            </div>
            <div class="card-body">
                <pre style="white-space: pre-wrap; font-family: inherit;">{{ $jobApplication->cover_letter }}</pre>
            </div>
        </div>
        @endif

        <!-- Resume Changes -->
        @if($jobApplication->resume_changes)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-earmark-diff"></i> Mudanças no Currículo</h5>
            </div>
            <div class="card-body">
                <ul>
                    @foreach($jobApplication->resume_changes as $change)
                    <li>{{ $change }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Error Details -->
        @if($jobApplication->error_message)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Erro</h5>
            </div>
            <div class="card-body">
                <p><strong>Mensagem:</strong></p>
                <pre class="bg-light p-2">{{ $jobApplication->error_message }}</pre>

                @if($jobApplication->error_trace)
                <details class="mt-3">
                    <summary><strong>Stack Trace</strong></summary>
                    <pre class="bg-light p-2 mt-2" style="font-size: 0.8rem; max-height: 300px; overflow-y: auto;">{{ $jobApplication->error_trace }}</pre>
                </details>
                @endif
            </div>
        </div>
        @endif
    </div>

    <!-- Right Column - Actions -->
    <div class="col-md-4">
        <!-- Actions Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-tools"></i> Ações</h5>
            </div>
            <div class="card-body">
                @if($jobApplication->canReprocess())
                <form action="{{ route('job-applications.reprocess', $jobApplication) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-arrow-clockwise"></i> Reprocessar
                    </button>
                </form>
                @endif
            <!-- Versions Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-layers"></i> Versões da Aplicação</h5>
                </div>
                <div class="card-body">
                    <ul>
                        @foreach($jobApplication->versions as $version)
                            <li>
                                <strong>Versão #{{ $version->version_number }}</strong> - Criado em: {{ $version->created_at->format('d/m/Y H:i:s') }}
                                <br>
                                <a href="{{ route('job-applications.version', [$jobApplication->id, $version->id]) }}" class="btn btn-sm btn-outline-info mt-1">Ver detalhes</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <!-- Extraction Versions Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-search"></i> Versões de Extração de Dados</h5>
                </div>
                <div class="card-body">
                    <ul>
                        @foreach($jobApplication->extractions as $extraction)
                            <li>
                                <strong>Versão #{{ $extraction->version_number }}</strong> - Criado em: {{ $extraction->created_at->format('d/m/Y H:i:s') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <!-- Scoring Versions Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> Versões de Score</h5>
                </div>
                <div class="card-body">
                    <ul>
                        @foreach($jobApplication->scorings as $scoring)
                            <li>
                                <strong>Score:</strong> {{ $scoring->scoring_score }} - Criado em: {{ $scoring->created_at->format('d/m/Y H:i:s') }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

                @if(!$jobApplication->isCompleted())
                <form action="{{ route('job-applications.mark-completed', $jobApplication) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-lg"></i> Marcar como Concluído
                    </button>
                </form>
                @endif

                @if($jobApplication->hasPdfs())
                <hr>
                <h6>Downloads:</h6>
                <a href="{{ route('job-applications.download-pdf', [$jobApplication, 'cover-letter']) }}"
                    class="btn btn-outline-primary w-100 mb-2">
                    <i class="bi bi-download"></i> Carta de Apresentação
                </a>
                <a href="{{ route('job-applications.download-pdf', [$jobApplication, 'resume']) }}"
                    class="btn btn-outline-primary w-100 mb-2">
                    <i class="bi bi-download"></i> Currículo
                </a>
                @endif

                <hr>

                <form action="{{ route('job-applications.destroy', $jobApplication) }}" method="POST"
                    onsubmit="return confirm('Tem certeza que deseja remover esta aplicação?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-100">
                        <i class="bi bi-trash"></i> Remover
                    </button>
                </form>
            </div>
        </div>

        <!-- Timeline Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Timeline</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    @if($jobApplication->created_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-secondary"></i>
                        <strong>Criado:</strong><br>
                        <small>{{ $jobApplication->created_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif

                    @if($jobApplication->started_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-info"></i>
                        <strong>Iniciado:</strong><br>
                        <small>{{ $jobApplication->started_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif

                    @if($jobApplication->classified_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-primary"></i>
                        <strong>Classificado:</strong><br>
                        <small>{{ $jobApplication->classified_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif

                    @if($jobApplication->scored_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-warning"></i>
                        <strong>Pontuado:</strong><br>
                        <small>{{ $jobApplication->scored_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif

                    @if($jobApplication->completed_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-success"></i>
                        <strong>Concluído:</strong><br>
                        <small>{{ $jobApplication->completed_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif

                    @if($jobApplication->failed_at)
                    <li class="mb-2">
                        <i class="bi bi-circle-fill text-danger"></i>
                        <strong>Falhou:</strong><br>
                        <small>{{ $jobApplication->failed_at->format('d/m/Y H:i:s') }}</small>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection