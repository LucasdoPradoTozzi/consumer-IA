# Consumer-IA — Documentação Completa do Projeto

> **Última atualização**: 2026-02-17
>
> Aplicação Laravel 12 para processamento automatizado de candidaturas a vagas de emprego,
> utilizando RabbitMQ (intake), Ollama LLM/OCR, e batch commands (processamento).

---

## Sumário

1. [Visão Geral da Arquitetura](#visão-geral-da-arquitetura)
2. [Stack Tecnológica](#stack-tecnológica)
3. [Fluxo de Processamento](#fluxo-de-processamento)
4. [Modelos (Database Schema)](#modelos-database-schema)
5. [Services](#services)
6. [Workers](#workers)
7. [Artisan Commands](#artisan-commands)
8. [Configurações](#configurações)
9. [Scheduler (Cron)](#scheduler-cron)
10. [Rotas Web](#rotas-web)
11. [Integração com IA (Ollama)](#integração-com-ia-ollama)
12. [Geração de PDFs](#geração-de-pdfs)
13. [Envio de Email](#envio-de-email)
14. [Currículo Dinâmico](#currículo-dinâmico)
15. [Docker & Infraestrutura](#docker--infraestrutura)
16. [Como Processar Vagas](#como-processar-vagas)

---

## Visão Geral da Arquitetura

A aplicação segue uma **arquitetura de pipeline em duas camadas**:

### Camada 1: Intake via RabbitMQ (fila)

O consumer RabbitMQ (`worker:consume`) escuta **3 filas** e é responsável apenas por:
- **Deduplicação + salvamento** de novas vagas no banco de dados
- **Mark-job-done**: marcar vaga como já aplicada
- **Reproccess-job**: reprocessar vaga com feedback adicional

Ao receber uma nova vaga, o consumer cria:
1. Um registro `JobApplication` com `status: pending`
2. Um registro `JobExtraction` (versão 1) com `extraction_data` populado a partir dos dados da vaga

**O consumer NÃO executa scoring, geração ou envio de email.**

### Camada 2: Processamento via Batch Commands (cron/scheduler)

Quatro commands Artisan são agendados via cron e executam as etapas restantes do pipeline:

1. `app:extract-pending-applications` — OCR de imagens (se houver)
2. `app:score-pending-extractions` — Scoring de compatibilidade via Ollama
3. `app:generate-pending-applications` — Geração de cover letter + currículo PDF
4. `app:send-pending-application-emails` — Envio de email com candidatura

```
┌─────────────────┐    ┌─────────────────┐    ┌──────────────────┐
│   RabbitMQ      │    │   Cron/Scheduler │    │   Banco de Dados │
│   (3 filas)     │    │   (4 commands)   │    │   (PostgreSQL)   │
├─────────────────┤    ├─────────────────┤    ├──────────────────┤
│ deduplication   │───▶│ 1. extract      │───▶│ job_applications │
│ mark-job-done   │    │ 2. score        │    │ job_extractions  │
│ reproccess-job  │    │ 3. generate     │    │ job_scorings     │
└─────────────────┘    │ 4. email        │    │ job_app_versions │
                       └─────────────────┘    │ job_deduplication│
                                              └──────────────────┘
```

---

## Stack Tecnológica

| Componente       | Tecnologia                          |
|:-----------------|:------------------------------------|
| Framework        | Laravel 12 (PHP 8.2+)              |
| Banco de Dados   | PostgreSQL                          |
| Message Broker   | RabbitMQ (via `php-amqplib`)        |
| IA / LLM         | Ollama (modelos locais)             |
| OCR              | Ollama Vision (modelos multimodais) |
| Cache / Lock     | Redis                               |
| PDF              | DomPDF (via `barryvdh/laravel-dompdf`) |
| Email            | Laravel Mail (SMTP)                 |
| Container        | Docker / Docker Compose             |

---

## Fluxo de Processamento

### Fluxo 1: Nova vaga (fila deduplication)

```
Mensagem RabbitMQ (fila "process-jobs")
    │
    ▼
RabbitConsumerService.handleMessage()
    │
    ▼
JobProcessorService.process()
    │
    ▼
processDeduplication()
    ├── Cria JobApplication (status: pending, job_data com dados da vaga)
    ├── DeduplicationWorker.process() → verifica duplicidade por link/conteúdo
    │   ├── Duplicado: log e para
    │   └── Novo: cria JobDeduplication e continua ▼
    └── Cria JobExtraction (version 1, extraction_data = job_data)
        │
        └── FIM do processamento RabbitMQ
```

### Fluxo 2: Pipeline batch (cron a cada minuto)

```
[1] app:extract-pending-applications
    ├── Passo 1 (Backfill): Busca JobApplication sem JobExtraction e cria extração inicial
    ├── Passo 2 (OCR): Busca JobExtraction com imagem no extraction_data
    ├── ExtractionWorker: OCR via Ollama Vision → atualiza extraction_data
    └── Se não tem imagem: extraction_data já está preenchido (skip)

[2] app:score-pending-extractions
    ├── Busca JobExtraction com extraction_data ≠ null e sem JobScoring
    ├── ScoringWorker: envia extraction_data + perfil candidato → Ollama
    └── Cria JobScoring (score 0-100, justificativa)

[3] app:generate-pending-applications
    ├── Busca JobScoring com score >= 70 e sem JobApplicationVersion
    ├── GenerationWorker: gera cover letter + currículo via Ollama
    ├── PdfService: gera PDFs da cover letter e currículo
    └── Cria JobApplicationVersion (cover_letter, resume_data, PDFs, completed=true)

[4] app:send-pending-application-emails
    ├── Busca JobApplicationVersion completed=true, email_sent=false
    ├── EmailWorker: envia email com PDFs anexados
    └── Marca email_sent=true
```

### Fluxo 3: Mark-job-done (fila mark-job-done)

```
Mensagem RabbitMQ → JobProcessorService.markJobAsDone()
    └── Atualiza JobApplication.status = "completed"
```

### Fluxo 4: Reprocessamento (fila reproccess-job)

```
Mensagem RabbitMQ → JobProcessorService.reprocessJob()
    ├── Reset JobApplication.status = "pending"
    └── Cria nova JobExtraction (version N+1) com feedback no extra_information
        └── Pipeline batch processará novamente
```

---

## Modelos (Database Schema)

### `job_applications`

| Coluna         | Tipo     | Descrição                                            |
|:---------------|:---------|:-----------------------------------------------------|
| `id`           | bigint   | PK auto-increment                                    |
| `raw_message`  | json     | Mensagem original do RabbitMQ (sanitizada)           |
| `job_data`     | json     | Dados da vaga (título, empresa, link, descrição etc) |
| `status`       | string   | `pending`, `processing`, `completed`, `failed`       |
| `created_at`   | timestamp|                                                       |
| `updated_at`   | timestamp|                                                       |

**Relacionamentos**: `hasMany` → JobExtraction, JobScoring, JobApplicationVersion, JobDeduplication

### `job_deduplication`

| Coluna              | Tipo      | Descrição                         |
|:--------------------|:----------|:----------------------------------|
| `id`                | bigint    | PK                                |
| `hash`              | string    | Hash único (link ou conteúdo)     |
| `source`            | string    | `link` ou `content`               |
| `original_link`     | string?   | Link original da vaga             |
| `original_content`  | text?     | Conteúdo original                 |
| `job_application_id`| FK        | → job_applications                |
| `first_seen_at`     | timestamp | Primeira vez que a vaga foi vista |

### `job_extractions`

| Coluna              | Tipo    | Descrição                                         |
|:--------------------|:--------|:--------------------------------------------------|
| `id`                | bigint  | PK                                                |
| `job_application_id`| FK      | → job_applications                                |
| `version_number`    | integer | Versão da extração (1, 2, 3...)                   |
| `extra_information` | text?   | Feedback adicional (reprocessamento)               |
| `extraction_data`   | json?   | Dados extraídos (job_data + OCR se houver imagem) |

**Unique**: `(job_application_id, version_number)`
**Relacionamentos**: `belongsTo` → JobApplication, `hasMany` → JobScoring

### `job_scorings`

| Coluna                | Tipo    | Descrição                             |
|:----------------------|:--------|:--------------------------------------|
| `id`                  | bigint  | PK                                    |
| `job_application_id`  | FK      | → job_applications                    |
| `extraction_version_id`| FK     | → job_extractions                     |
| `scoring_score`       | integer | Score 0-100                           |
| `scoring_data`        | json    | Dados detalhados do scoring (LLM)     |

**Relacionamentos**: `belongsTo` → JobApplication, JobExtraction; `hasMany` → JobApplicationVersion

### `job_application_versions`

| Coluna              | Tipo    | Descrição                                    |
|:--------------------|:--------|:---------------------------------------------|
| `id`                | bigint  | PK                                           |
| `job_application_id`| FK      | → job_applications                           |
| `scoring_id`        | FK      | → job_scorings                               |
| `version_number`    | integer | Versão dos materiais gerados                 |
| `cover_letter`      | text    | Cover letter gerada pelo LLM                 |
| `email_subject`     | string  | Assunto do email                             |
| `email_body`        | text    | Corpo do email                               |
| `resume_data`       | json    | Dados do currículo (JSON com seções)          |
| `resume_path`       | string  | Caminho do PDF do currículo                  |
| `email_sent`        | boolean | Se o email foi enviado                       |
| `completed`         | boolean | Se a geração está completa (cover + resume)  |
| `resume_config`     | json?   | Configuração dinâmica do template de currículo|

**Unique**: `(scoring_id, version_number)`

---

## Services

### `RabbitConsumerService`

**Arquivo**: `app/Services/RabbitConsumerService.php`

Responsável por:
- Conectar-se ao RabbitMQ
- Declarar e escutar as 3 filas configuradas
- Rotear mensagens para o handler correto via `handleMessage()`
- Transformar mensagens legadas (formato simples) para `JobPayload`
- Gerenciar shutdown graceful e reconexão

**Filas escutadas**:
| Fila                | Handler                                    |
|:--------------------|:-------------------------------------------|
| `deduplication`     | `processJobApplication()` → `JobProcessorService.process()` |
| `mark-job-done`     | `markJobDone()`                            |
| `reproccess-job`    | `reprocessJob()`                           |

### `JobProcessorService`

**Arquivo**: `app/Services/JobProcessorService.php`

Responsável por:
- Processar mensagens da fila de deduplicação
- Criar `JobApplication` e `JobExtraction` no banco
- Marcar jobs como "done" ou reprocessar com feedback

**Métodos públicos**:
- `process(JobPayload, queueName, rawMessage)` — Processa nova vaga
- `markJobAsDone(jobId)` — Marca vaga como aplicada
- `reprocessJob(jobId, message)` — Reprocessa vaga com feedback

**Dependências**: apenas `DeduplicationWorker`

### `JobCoordinatorService`

**Arquivo**: `app/Services/JobCoordinatorService.php`

Responsável por publicar mensagens nas filas RabbitMQ. Utilizado pelo command `job:process` para enviar vagas à fila de deduplicação.

**Métodos**:
- `sendToDeduplication(JobPayload)` — Publica na fila de deduplicação

### `OllamaService`

**Arquivo**: `app/Services/OllamaService.php`

Interface com o servidor Ollama para:
- Geração de texto (LLM)
- Extração de texto de imagens (Vision/OCR)
- Verificação de disponibilidade e modelos

### `PromptBuilderService`

**Arquivo**: `app/Services/PromptBuilderService.php`

Constrói prompts estruturados para o Ollama a partir de templates configurados em `config/prompts.php`. Utilizado por `ScoringWorker` e `GenerationWorker`.

### `PdfService`

**Arquivo**: `app/Services/PdfService.php`

Gera PDFs usando DomPDF:
- Cover letter PDF (com dados da vaga e candidato)
- Currículo PDF (a partir de template Blade dinâmico)

### `EmailService`

**Arquivo**: `app/Services/EmailService.php`

Envia emails de candidatura com:
- Cover letter no corpo ou anexo
- Currículo PDF anexado
- Subject e body configuráveis

---

## Workers

Cada worker processa uma etapa específica do pipeline e é chamado pelos batch commands.

### `DeduplicationWorker`

**Arquivo**: `app/Services/Workers/DeduplicationWorker.php`

- Verifica duplicidade por **link** (hash MD5) e por **conteúdo** (hash MD5)
- Se novo: cria registro em `job_deduplication`, retorna `true`
- Se duplicado: retorna `false`

**Dependências**: nenhuma (construtor vazio)

### `ExtractionWorker`

**Arquivo**: `app/Services/Workers/ExtractionWorker.php`

- Processa a extração mais recente da `JobApplication`
- Se há imagem base64 no `extraction_data`: faz OCR via Ollama Vision
- Atualiza `extraction_data` com o texto extraído

**Dependências**: `OllamaService`

### `ScoringWorker`

**Arquivo**: `app/Services/Workers/ScoringWorker.php`

- Busca extractions sem scoring
- Envia `extraction_data` + perfil do candidato para Ollama
- Parsea resposta JSON do LLM (score 0-100 + justificativa)
- Cria registro `JobScoring`

**Dependências**: `OllamaService`, `PromptBuilderService`

### `GenerationWorker`

**Arquivo**: `app/Services/Workers/GenerationWorker.php`

- Processa scorings com nota >= threshold (config `processing.score_threshold`, padrão 70)
- Gera cover letter via Ollama
- Gera dados do currículo adaptado via Ollama
- Cria PDFs (cover letter + currículo) via `PdfService`
- Cria/atualiza `JobApplicationVersion`

**Dependências**: `OllamaService`, `PromptBuilderService`, `PdfService`

### `EmailWorker`

**Arquivo**: `app/Services/Workers/EmailWorker.php`

- Busca `JobApplicationVersion` com `completed=true` e `email_sent=false`
- Envia email via `EmailService`
- Marca `email_sent=true`

**Dependências**: `EmailService` (via `app()`)

---

## Artisan Commands

### Commands de Pipeline (batch)

| Command                                | Descrição                                        | Lock                                    |
|:---------------------------------------|:-------------------------------------------------|:----------------------------------------|
| `app:extract-pending-applications`     | OCR de imagens pendentes                         | `extract-pending-applications-lock` 600s|
| `app:score-pending-extractions`        | Scoring de extractions sem score                 | `score-pending-extractions-lock` 600s   |
| `app:generate-pending-applications`    | Geração de materiais para vagas com score alto   | `generate-pending-applications-lock` 600s|
| `app:send-pending-application-emails`  | Envio de emails pendentes (com `--limit`)        | `send-pending-application-emails-lock` 600s|

Todos usam `cache()->lock()` (Redis) para evitar concorrência.

### Commands de Infraestrutura

| Command                                | Descrição                                        |
|:---------------------------------------|:-------------------------------------------------|
| `worker:consume [--once] [--timeout=N]`| Consome mensagens do RabbitMQ                    |
| `worker:check`                         | Verifica saúde dos serviços (RabbitMQ, Ollama, DB, Redis) |
| `rabbitmq:publish <queue>`             | Publica mensagem manualmente em uma fila         |
| `job:process --job-data=JSON`          | Envia vaga para fila de deduplicação via CLI     |
| `app:queue-status`                     | Status das filas do RabbitMQ                     |

---

## Configurações

### `config/rabbitmq.php`

```php
'host'     => env('RABBITMQ_HOST', 'localhost'),
'port'     => env('RABBITMQ_PORT', 5672),
'user'     => env('RABBITMQ_USER', 'guest'),
'password' => env('RABBITMQ_PASSWORD', 'guest'),
'vhost'    => env('RABBITMQ_VHOST', '/'),

'queues' => [
    'deduplication' => env('RABBITMQ_QUEUE_PROCESS', 'process-jobs'),
    'mark-job-done' => env('RABBITMQ_QUEUE_MARK_DONE', 'mark-job-done'),
    'reproccess-job' => env('RABBITMQ_QUEUE_REPROCESS', 'reproccess-job'),
],

'prefetch_count'     => 1,
'consumer_tag'       => 'laravel_consumer',
'connection_timeout' => 3.0,
'read_write_timeout' => 3.0,
'heartbeat'          => 60,
'keepalive'          => true,
```

### `config/processing.php`

```php
'score_threshold' => env('PROCESSING_SCORE_THRESHOLD', 70),
```

### `config/ollama.php`

Configuração do servidor Ollama (URL, perfis, modelos, timeouts).

### `config/candidate.php`

Perfil do candidato usado no scoring e geração (nome, email, skills, experiência etc).

### `config/prompts.php`

Templates de prompts usados pelo `PromptBuilderService` para scoring e geração.

### `config/curriculum.php` / `config/curriculum_en.php`

Configuração do currículo dinâmico (seções, dados, template Blade).

---

## Scheduler (Cron)

**Arquivo**: `routes/console.php`

Pipeline agendado a cada minuto:

```
1. app:extract-pending-applications   → OCR de imagens
2. app:score-pending-extractions      → Scoring de compatibilidade
3. app:generate-pending-applications  → Geração de materiais
4. app:send-pending-application-emails → Envio de email
5. worker:consume                      → Consumer RabbitMQ (intake)
```

Todos com `withoutOverlapping()` para evitar concorrência e `runInBackground()`.

Logs salvos em `storage/logs/`:
- `extract-pending.log`
- `score-pending.log`
- `generate-pending.log`
- `send-pending-emails.log`
- `worker-schedule.log`

---

## Rotas Web

**Arquivo**: `routes/web.php`

| Rota                           | Descrição                          |
|:-------------------------------|:-----------------------------------|
| `/job-applications`            | Dashboard de vagas                 |
| `/job-applications/{id}`       | Detalhes da vaga                   |
| `/logs`                        | Visualizador de logs               |
| `/curriculum/template`         | Preview do template de currículo   |

---

## Integração com IA (Ollama)

### Modelos usados

- **LLM**: para scoring (análise de compatibilidade), geração de cover letter e dados do currículo
- **Vision/OCR**: para extração de texto de imagens de vagas (multimodal)

### Fluxo de IA

1. **Scoring**: `extraction_data` + perfil do candidato → prompt de scoring → Ollama → JSON `{score: 0-100, justification: "..."}`
2. **Cover Letter**: dados da vaga + perfil + score → prompt de geração → Ollama → texto da cover letter
3. **Resume Data**: dados da vaga + perfil → prompt de currículo → Ollama → JSON com seções do currículo
4. **OCR**: imagem base64 → Ollama Vision → texto extraído

**Os prompts NÃO foram alterados nesta refatoração.** Eles são mantidos em `config/prompts.php` e carregados pelo `PromptBuilderService`.

---

## Geração de PDFs

### Cover Letter PDF
- Gerado por `PdfService.generateCoverLetterPdf()`
- Template Blade com dados da vaga e candidato
- Salvo em `storage/app/pdfs/`

### Currículo PDF
- Gerado por `PdfService.generateCurriculumPdf()`
- Template Blade dinâmico usando `resume_config` do `JobApplicationVersion`
- Configuração base em `config/curriculum.php`
- Salvo em `storage/app/pdfs/`

---

## Envio de Email

- Utiliza `EmailService` com Laravel Mail (SMTP)
- Subject e body configuráveis por vaga
- PDFs anexados (cover letter + currículo)
- Email do remetente configurado via perfil do candidato (`config/candidate.php`)

---

## Currículo Dinâmico

O sistema permite que o LLM adapte o currículo para cada vaga:
1. `GenerationWorker` chama Ollama pedindo dados do currículo em JSON
2. O JSON resultante é mesclado com a configuração base (`config/curriculum.php`)
3. `PdfService.generateCurriculumPdf()` renderiza o template Blade com os dados combinados
4. O campo `resume_config` do `JobApplicationVersion` guarda a configuração usada

---

## Docker & Infraestrutura

### Serviços Docker

| Container          | Serviço     | Porta |
|:-------------------|:------------|:------|
| `consumerIA-php`   | Laravel App | -     |
| `consumerIA-postgres` | PostgreSQL | 5432  |
| `consumerIA-rabbitmq` | RabbitMQ  | 5672, 15672 |
| `consumerIA-redis` | Redis       | 6379  |
| `consumerIA-ollama`| Ollama      | 11434 |

### Commands Docker úteis

```bash
# Verificar saúde dos serviços
docker exec consumerIA-php php artisan worker:check

# Processar vagas manualmente
docker exec consumerIA-php php artisan rabbitmq:publish process-jobs \
  --job-title="Dev Laravel" --company="Empresa" --description="Vaga..."

# Consumer RabbitMQ (intake)
docker exec consumerIA-php php artisan worker:consume --timeout=30

# Pipeline batch manual (em ordem)
docker exec consumerIA-php php artisan app:extract-pending-applications
docker exec consumerIA-php php artisan app:score-pending-extractions
docker exec consumerIA-php php artisan app:generate-pending-applications
docker exec consumerIA-php php artisan app:send-pending-application-emails

# Verificar estado no banco
docker exec consumerIA-php php artisan tinker --execute="
  \$ja = \App\Models\JobApplication::latest()->first();
  echo 'ID: ' . \$ja->id . PHP_EOL;
  echo 'Status: ' . \$ja->status . PHP_EOL;
  echo 'Extractions: ' . \$ja->extractions()->count() . PHP_EOL;
  echo 'Scorings: ' . \$ja->scorings()->count() . PHP_EOL;
  echo 'Versions: ' . \$ja->versions()->count() . PHP_EOL;
"
```

---

## Como Processar Vagas

### Via RabbitMQ (produção)

1. Um sistema externo publica mensagem na fila `process-jobs` com payload:
```json
{
  "type": "job_application",
  "data": {
    "job": {
      "title": "Desenvolvedor Laravel",
      "company": "Empresa XYZ",
      "link": "https://example.com/vaga/123",
      "description": "Requisitos..."
    },
    "candidate": {
      "name": "João Silva",
      "email": "joao@email.com"
    }
  }
}
```
2. O consumer salva no banco e cria extraction
3. O scheduler processa as etapas restantes automaticamente

### Via CLI (desenvolvimento/teste)

```bash
docker exec consumerIA-php php artisan job:process \
  --job-data='{"title":"Dev Laravel","company":"Empresa","link":"https://example.com/vaga","description":"Requisitos..."}'
```

### Formato legado de mensagem

O `RabbitConsumerService.transformLegacyMessageFormat()` aceita mensagens simples:
```json
{
  "link": "https://example.com/vaga",
  "email": "rh@empresa.com",
  "job_info": {"title": "Dev", "company": "Empresa"}
}
```
E as transforma automaticamente no formato `JobPayload` esperado.
