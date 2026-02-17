@extends('layouts.app')

@section('title', 'Worker Logs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>📋 Worker Logs</h2>
    <div>
        <span id="worker-status-badge" class="badge bg-secondary me-2">
            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
            Verificando...
        </span>
        <button class="btn btn-sm btn-outline-primary" id="refresh-btn">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
        <button class="btn btn-sm btn-outline-danger" id="clear-btn">
            <i class="bi bi-trash"></i> Limpar
        </button>
        <a href="{{ route('job-applications.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Filtrar por nível:</label>
                        <select class="form-select form-select-sm" id="log-filter">
                            <option value="">Todos</option>
                            <option value="info">INFO</option>
                            <option value="warning">WARNING</option>
                            <option value="error">ERROR</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Linhas:</label>
                        <select class="form-select form-select-sm" id="log-lines">
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                            <option value="200">200</option>
                            <option value="500">500</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Auto-atualizar:</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto-refresh" checked>
                            <label class="form-check-label" for="auto-refresh">
                                A cada <span id="refresh-interval-text">5</span>s
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Intervalo (s):</label>
                        <input type="range" class="form-range" id="refresh-interval" min="2" max="30" value="5" step="1">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Queue Messages Section -->
<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-cloud-arrow-down"></i> Últimas Mensagens das Filas (Redis)
                </span>
                <small class="text-muted">
                    Atualiza automático: <span id="queue-last-update">Nunca</span>
                </small>
            </div>
            <div class="card-body" id="queue-messages-container">
                <div class="text-center text-muted py-3">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <span class="ms-2">Carregando mensagens das filas...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="bi bi-terminal"></i> Logs em Tempo Real
        </span>
        <small class="text-muted">
            Última atualização: <span id="last-update">Nunca</span>
        </small>
    </div>
    <div class="card-body p-0">
        <div id="logs-container" style="height: 600px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; font-family: 'Courier New', monospace; font-size: 13px; padding: 15px;">
            <div class="text-center text-muted py-5">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Carregando logs...</p>
            </div>
        </div>
    </div>
</div>

<style>
    .log-line {
        padding: 4px 0;
        border-bottom: 1px solid #2d2d2d;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .log-line:hover {
        background: #2d2d2d;
    }

    .log-timestamp {
        color: #858585;
        margin-right: 10px;
    }

    .log-level {
        margin-right: 10px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
    }

    .log-level-ERROR {
        background: #dc3545;
        color: white;
    }

    .log-level-WARNING {
        background: #ffc107;
        color: #000;
    }

    .log-level-INFO {
        background: #17a2b8;
        color: white;
    }

    .log-level-DEBUG {
        background: #6c757d;
        color: white;
    }

    .log-message {
        color: #d4d4d4;
    }

    .worker-active {
        background-color: #28a745 !important;
    }

    .worker-idle {
        background-color: #ffc107 !important;
    }

    .worker-inactive {
        background-color: #6c757d !important;
    }
</style>

@push('scripts')
<script>
    let autoRefreshInterval = null;
    let isAutoRefresh = true;
    let refreshIntervalSeconds = 5;

    // Check worker status
    function checkWorkerStatus() {
        fetch('{{ route("logs.worker-status") }}')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('worker-status-badge');
                badge.className = 'badge me-2';

                if (data.status === 'active') {
                    badge.classList.add('worker-active');
                    badge.innerHTML = '<i class="bi bi-circle-fill me-1"></i> Worker Ativo';
                } else if (data.status === 'idle') {
                    badge.classList.add('worker-idle');
                    badge.innerHTML = '<i class="bi bi-circle-fill me-1"></i> Worker Ocioso';
                } else {
                    badge.classList.add('worker-inactive');
                    badge.innerHTML = '<i class="bi bi-circle me-1"></i> Worker Inativo';
                }

                badge.title = data.message;
            })
            .catch(error => {
                console.error('Error checking worker status:', error);
            });
    }

    // Fetch logs
    function fetchLogs() {
        const filter = document.getElementById('log-filter').value;
        const lines = document.getElementById('log-lines').value;

        fetch(`{{ route('logs.fetch') }}?filter=${filter}&lines=${lines}`)
            .then(response => response.json())
            .then(data => {
                renderLogs(data.logs);
                document.getElementById('last-update').textContent = new Date().toLocaleTimeString('pt-BR');
            })
            .catch(error => {
                console.error('Error fetching logs:', error);
                document.getElementById('logs-container').innerHTML = `
                <div class="text-center text-danger py-5">
                    <i class="bi bi-exclamation-triangle"></i>
                    <p class="mt-2">Erro ao carregar logs</p>
                </div>
            `;
            });
    }

    // Render logs
    function renderLogs(logs) {
        const container = document.getElementById('logs-container');

        if (logs.length === 0) {
            container.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox"></i>
                <p class="mt-2">Nenhum log encontrado</p>
            </div>
        `;
            return;
        }

        const html = logs.map(log => {
            const level = log.level || 'INFO';
            return `
            <div class="log-line">
                ${log.timestamp ? `<span class="log-timestamp">[${log.timestamp}]</span>` : ''}
                <span class="log-level log-level-${level}">${level}</span>
                <span class="log-message">${escapeHtml(log.message)}</span>
            </div>
        `;
        }).join('');

        container.innerHTML = html;

        // Auto-scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Fetch queue messages from Redis
    function fetchQueueMessages() {
        fetch('{{ route("logs.queue-messages") }}')
            .then(response => response.json())
            .then(data => {
                displayQueueMessages(data.messages);

                // Update timestamp
                const now = new Date(data.timestamp);
                document.getElementById('queue-last-update').textContent = now.toLocaleTimeString('pt-BR');
            })
            .catch(error => {
                console.error('Error fetching queue messages:', error);
                document.getElementById('queue-messages-container').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-x-circle"></i> Erro ao carregar mensagens das filas
                    </div>
                `;
            });
    }

    // Display queue messages
    function displayQueueMessages(messages) {
        const container = document.getElementById('queue-messages-container');

        let html = '';

        messages.forEach(msg => {
            const statusBadge = msg.status === 'success' ? 'success' :
                msg.status === 'processing' ? 'warning' :
                msg.status === 'failed' ? 'danger' : 'secondary';

            const statusIcon = msg.status === 'success' ? '✓' :
                msg.status === 'processing' ? '⏳' :
                msg.status === 'failed' ? '✗' : '○';

            html += `
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">
                            <span class="badge bg-primary">${msg.queue}</span>
                        </h6>
                        <span class="badge bg-${statusBadge}">${statusIcon} ${msg.status.toUpperCase()}</span>
                    </div>
            `;

            if (msg.has_data) {
                if (msg.timestamp) {
                    const time = new Date(msg.timestamp);
                    html += `<small class="text-muted"><i class="bi bi-clock"></i> ${time.toLocaleString('pt-BR')}</small> `;
                }

                if (msg.size) {
                    html += `<small class="text-muted"><i class="bi bi-file-earmark"></i> ${(msg.size / 1024).toFixed(2)} KB</small>`;
                }

                if (msg.error) {
                    html += `<div class="alert alert-danger alert-sm mt-2 mb-2"><i class="bi bi-exclamation-triangle"></i> ${msg.error}</div>`;
                }

                if (msg.data) {
                    html += '<div class="mt-2"><small class="text-muted"><strong>Dados:</strong></small>';

                    if (msg.data.job_id) {
                        html += `<div><small><strong>Job ID:</strong> ${msg.data.job_id}</small></div>`;
                    }

                    if (msg.data.type) {
                        html += `<div><small><strong>Type:</strong> ${msg.data.type}</small></div>`;
                    }

                    if (msg.data.data && msg.data.data.job && msg.data.data.job.title) {
                        html += `<div><small><strong>Title:</strong> ${msg.data.data.job.title}</small></div>`;
                    }

                    if (msg.data.data && msg.data.data.job && msg.data.data.job.company) {
                        html += `<div><small><strong>Company:</strong> ${msg.data.data.job.company}</small></div>`;
                    }

                    // Show JSON preview
                    html += `
                        <details class="mt-2">
                            <summary style="cursor: pointer;"><small>Ver JSON completo</small></summary>
                            <pre class="bg-dark text-light p-2 rounded mt-2" style="font-size: 11px; max-height: 300px; overflow-y: auto;">${JSON.stringify(msg.data, null, 2)}</pre>
                        </details>
                    </div>
                    `;
                }
            } else {
                html += '<div class="text-muted"><small>Nenhuma mensagem recebida ainda</small></div>';
            }

            html += '</div>';
        });

        container.innerHTML = html || '<div class="text-center text-muted py-3">Nenhuma fila configurada</div>';
    }

    // Clear logs
    function clearLogs() {
        if (!confirm('Tem certeza que deseja limpar todos os logs?')) {
            return;
        }

        fetch('{{ route("logs.clear") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                fetchLogs();
            })
            .catch(error => {
                console.error('Error clearing logs:', error);
                alert('Erro ao limpar logs');
            });
    }

    // Setup auto-refresh
    function setupAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }

        if (isAutoRefresh) {
            autoRefreshInterval = setInterval(() => {
                fetchLogs();
                fetchQueueMessages();
                checkWorkerStatus();
            }, refreshIntervalSeconds * 1000);
        }
    }

    // Event listeners
    document.getElementById('refresh-btn').addEventListener('click', () => {
        fetchLogs();
        fetchQueueMessages();
        checkWorkerStatus();
    });

    document.getElementById('clear-btn').addEventListener('click', clearLogs);

    document.getElementById('log-filter').addEventListener('change', fetchLogs);
    document.getElementById('log-lines').addEventListener('change', fetchLogs);

    document.getElementById('auto-refresh').addEventListener('change', (e) => {
        isAutoRefresh = e.target.checked;
        setupAutoRefresh();
    });

    document.getElementById('refresh-interval').addEventListener('input', (e) => {
        refreshIntervalSeconds = parseInt(e.target.value);
        document.getElementById('refresh-interval-text').textContent = refreshIntervalSeconds;
        setupAutoRefresh();
    });

    // Initialize
    fetchLogs();
    fetchQueueMessages();
    checkWorkerStatus();
    setupAutoRefresh();

    // Check worker status every 10 seconds
    setInterval(checkWorkerStatus, 10000);
</script>
@endpush
@endsection