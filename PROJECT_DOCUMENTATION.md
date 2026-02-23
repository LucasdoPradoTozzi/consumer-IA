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
16. [Privacidade e Segurança](#privacidade-e-segurança)
17. [Como Processar Vagas](#como-processar-vagas)

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

| Componente     | Tecnologia                                              |
| :------------- | :------------------------------------------------------ |
| Framework      | Laravel 12 (PHP 8.2+)                                   |
| Banco de Dados | PostgreSQL                                              |
| Message Broker | RabbitMQ (via `php-amqplib`)                            |
| IA / LLM       | Google AI Studio (Gemini) + Ollama (local), via LlmService orquestrador |
| OCR            | Modelos multimodais via LlmService                      |
| Cache / Lock   | Redis                                                   |
| PDF            | DomPDF (via `barryvdh/laravel-dompdf`)                  |
| Email          | Laravel Mail (SMTP)                                     |
| Container      | Docker / Docker Compose                                 |

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

| Coluna        | Tipo      | Descrição                                            |
| :------------ | :-------- | :--------------------------------------------------- |
| `id`          | bigint    | PK auto-increment                                    |
| `raw_message` | json      | Mensagem original do RabbitMQ (sanitizada)           |
| `job_data`    | json      | Dados da vaga (título, empresa, link, descrição etc) |
| `status`      | string    | `pending`, `processing`, `completed`, `failed`       |
| `created_at`  | timestamp |                                                      |
| `updated_at`  | timestamp |                                                      |

**Relacionamentos**: `hasMany` → JobExtraction, JobScoring, JobApplicationVersion, JobDeduplication

### `job_deduplication`

| Coluna               | Tipo      | Descrição                         |
| :------------------- | :-------- | :-------------------------------- |
| `id`                 | bigint    | PK                                |
| `hash`               | string    | Hash único (link ou conteúdo)     |
| `source`             | string    | `link` ou `content`               |
| `original_link`      | string?   | Link original da vaga             |
| `original_content`   | text?     | Conteúdo original                 |
| `job_application_id` | FK        | → job_applications                |
| `first_seen_at`      | timestamp | Primeira vez que a vaga foi vista |

### `job_extractions`

| Coluna               | Tipo    | Descrição                                         |
| :------------------- | :------ | :------------------------------------------------ |
| `id`                 | bigint  | PK                                                |
| `job_application_id` | FK      | → job_applications                                |
| `version_number`     | integer | Versão da extração (1, 2, 3...)                   |
| `extra_information`  | text?   | Feedback adicional (reprocessamento)              |
| `extraction_data`    | json?   | Dados extraídos (job_data + OCR se houver imagem) |

**Unique**: `(job_application_id, version_number)`
**Relacionamentos**: `belongsTo` → JobApplication, `hasMany` → JobScoring

### `job_scorings`

| Coluna                  | Tipo    | Descrição                         |
| :---------------------- | :------ | :-------------------------------- |
| `id`                    | bigint  | PK                                |
| `job_application_id`    | FK      | → job_applications                |
| `extraction_version_id` | FK      | → job_extractions                 |
| `scoring_score`         | integer | Score 0-100                       |
| `scoring_data`          | json    | Dados detalhados do scoring (LLM) |

**Relacionamentos**: `belongsTo` → JobApplication, JobExtraction; `hasMany` → JobApplicationVersion

### `job_application_versions`

| Coluna               | Tipo    | Descrição                                      |
| :------------------- | :------ | :--------------------------------------------- |
| `id`                 | bigint  | PK                                             |
| `job_application_id` | FK      | → job_applications                             |
| `scoring_id`         | FK      | → job_scorings                                 |
| `version_number`     | integer | Versão dos materiais gerados                   |
| `cover_letter`       | text    | Cover letter gerada pelo LLM                   |
| `email_subject`      | string  | Assunto do email                               |
| `email_body`         | text    | Corpo do email                                 |
| `resume_data`        | json    | Dados do currículo (JSON com seções)           |
| `resume_path`        | string  | Caminho do PDF do currículo                    |
| `email_sent`         | boolean | Se o email foi enviado                         |
| `completed`          | boolean | Se a geração está completa (cover + resume)    |
| `resume_config`      | json?   | Configuração dinâmica do template de currículo |

**Unique**: `(scoring_id, version_number)`

### `llm_providers`

Registro dos provedores de IA disponíveis.

| Coluna            | Tipo    | Descrição                                                  |
| :---------------- | :------ | :--------------------------------------------------------- |
| `id`              | bigint  | PK                                                         |
| `slug`            | string  | Único: `google`, `ollama`                                  |
| `name`            | string  | Nome de exibição                                           |
| `is_active`       | boolean | Se o provedor está ativo                                   |
| `priority`        | integer | Prioridade global (1 = preferido)                          |
| `api_key_env_var` | string? | Nome da variável `.env` com a API key (ex: `GOOGLEAI_API_KEY`) |
| `service_class`   | string  | Classe PHP de serviço (ex: `App\Services\GoogleAiStudioService`) |

**Relacionamentos**: `hasMany` → LlmModel

### `llm_models`

Modelos individuais com capability, ranking e limites de quota.

| Coluna              | Tipo    | Descrição                                           |
| :------------------ | :------ | :-------------------------------------------------- |
| `id`                | bigint  | PK                                                  |
| `llm_provider_id`   | FK      | → `llm_providers`                                   |
| `name`              | string  | Nome do modelo: `gemini-1.5-flash-latest`           |
| `capability`        | string  | `text`, `image`, `multimodal`, `open-weight`        |
| `ranking`           | integer | Prioridade dentro do capability (1 = melhor)        |
| `is_active`         | boolean | Se o modelo está ativo                              |
| `quota_per_minute`  | integer?| Máx requisições/minuto (null = sem limite)         |
| `quota_per_day`     | integer?| Máx requisições/dia (null = sem limite)            |
| `tokens_per_minute` | integer?| Uso futuro                                          |

**Unique**: `(llm_provider_id, name)`
**Relacionamentos**: `belongsTo` → LlmProvider, `hasMany` → LlmUsageLog

### `llm_usage_logs`

Log de cada chamada de LLM para controle de quotas e auditoria.

| Coluna             | Tipo      | Descrição                              |
| :----------------- | :-------- | :------------------------------------- |
| `id`               | bigint    | PK                                     |
| `llm_model_id`     | FK        | → `llm_models`                         |
| `capability`       | string    | O que foi requisitado                  |
| `response_time_ms` | integer   | Duração da chamada em ms               |
| `success`          | boolean   | Se a chamada foi bem-sucedida          |
| `error_message`    | text?     | Mensagem de erro se falhou             |
| `metadata`         | json?     | Contexto extra (job_id etc.)           |
| `called_at`        | timestamp | Quando a chamada foi feita (indexado)  |

**Relacionamentos**: `belongsTo` → LlmModel

### `candidate_profiles` (Novo)

Tabela principal para armazenar os dados do candidato (antigo `candidate-profile.json`).

| Coluna | Tipo | Descrição |
| :--- | :--- | :--- |
| `id` | bigint | PK |
| `name` | string | Nome do candidato |
| `email` | string | Email de contato |
| `phone` | string | Telefone |
| `summary` | text | Resumo profissional |
| `seniority` | string | Nível de senioridade conjunta |
| `linkedin` | string | Link do LinkedIn |
| `github` | string | Link do Github |
| `remote` | boolean | Aceita trabalho remoto |
| `hybrid` | boolean | Aceita trabalho híbrido |
| `onsite` | boolean | Aceita trabalho presencial |
| `willing_to_relocate` | boolean | Disponibilidade para mudança |
| `availability` | string | Disponibilidade para iniciar |

**Relacionamentos**: `hasMany` → candidate_skills, candidate_experiences, candidate_educations, candidate_certifications, candidate_languages, candidate_locations, candidate_contract_types

### `candidate_skills`, `candidate_experiences`, etc.

Tabelas de apoio para estrutura relacional do candidato (1:N com `candidate_profiles`). 
*Nota: A tabela `candidate_skills` armazena `experience_years` (inteiro) em vez de níveis em texto.*

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
| Fila | Handler |
|:--------------------|:-------------------------------------------|
| `deduplication` | `processJobApplication()` → `JobProcessorService.process()` |
| `mark-job-done` | `markJobDone()` |
| `reproccess-job` | `reprocessJob()` |

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

Constrói prompts estruturados para o Ollama a partir de templates centralizados na nova configuração `config/prompts.php`. Isso substitui a lógica antiga de prompts "hardcoded".

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
- Email do remetente configurado via banco de dados através do `CandidateProfileService` (`config/candidate.php`)

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

| Command                               | Descrição                                      | Lock                                        |
| :------------------------------------ | :--------------------------------------------- | :------------------------------------------ |
| `app:extract-pending-applications`    | OCR de imagens pendentes                       | `extract-pending-applications-lock` 600s    |
| `app:score-pending-extractions`       | Scoring de extractions sem score               | `score-pending-extractions-lock` 600s       |
| `app:generate-pending-applications`   | Geração de materiais para vagas com score alto | `generate-pending-applications-lock` 600s   |
| `app:send-pending-application-emails` | Envio de emails pendentes (com `--limit`)      | `send-pending-application-emails-lock` 600s |

Todos usam `cache()->lock()` (Redis) para evitar concorrência.

### Commands de Infraestrutura

| Command                                 | Descrição                                                 |
| :-------------------------------------- | :-------------------------------------------------------- |
| `worker:consume [--once] [--timeout=N]` | Consome mensagens do RabbitMQ                             |
| `worker:check`                          | Verifica saúde dos serviços (RabbitMQ, Ollama, DB, Redis) |
| `rabbitmq:publish <queue>`              | Publica mensagem manualmente em uma fila                  |
| `job:process --job-data=JSON`           | Envia vaga para fila de deduplicação via CLI              |
| `app:queue-status`                      | Status das filas do RabbitMQ                              |

---

## Configurações

### `config/rabbitmq.php`

```php
'host'     => env('RABBITMQ_HOST', 'localhost'),
// ... (outras configurações de conexão e filas)
```

### `config/processing.php`

```php
'score_threshold' => env('PROCESSING_SCORE_THRESHOLD', 70),
```

### `config/ollama.php`

Configuração do servidor Ollama. Recém-atualizado para suportar **Perfies de Hardware**:

- `low`: Modelo leve (7B)
- `medium`: Modelo balanceado (14B)
- `high`: Modelo de produção/inteligente (32B)

### `config/candidate.php` e `CandidateProfileService`

Configuração legada e serviço de acesso seguro aos dados do perfil do candidato.
Historicamente lia de `candidate-profile.json`, mas agora age como um *Facade* em cima do `CandidateProfileService`, buscando os dados do banco de dados relacional e abstraindo a estrutura JSON esperada pelos LLMs.

### `config/prompts.php` (Novo)

Centraliza todos os templates de prompt do sistema:

- `extraction`: Extração de dados da vaga
- `scoring`: Avaliação de compatibilidade
- `cover_letter`: Geração de carta de apresentação
- `resume_adjustment`: Adaptação de currículo
- `email`: Geração de corpo de email

### `config/curriculum.php` / `config/curriculum_en.php`

Configuração do currículo dinâmico. Agora utiliza loaders para buscar dados do `config/candidate.php` em vez de ter dados hardcoded.

---

## Scheduler (Cron)

**Arquivo**: `routes/console.php`

Pipeline agendado a cada minuto. Todos com `withoutOverlapping()` e `runInBackground()`.

---

## Rotas Web

**Arquivo**: `routes/web.php`

Dashboard básico para monitoramento de vagas e logs.

---

## Integração com IA (LLM Orchestration)

### Arquitetura de Orquestração

Todas as requisições de IA passam pelo **`LlmService`**, que automaticamente:

1. **Seleciona o melhor modelo** baseado na capability solicitada (`text`, `image`, `multimodal`, `open-weight`)
2. **Respeita prioridades** — tenta provedores na ordem de prioridade (Google primeiro, Ollama depois)
3. **Controla quotas** — verifica limites por minuto/dia antes de chamar, faz fallback automático
4. **Loga cada chamada** — registra em `llm_usage_logs` para auditoria e controle

#### Uso Básico:

```php
$llm = app(\App\Services\LlmService::class);

// Texto (default)
$resposta = $llm->generateText('Seu prompt aqui');

// Especificar capability
$resposta = $llm->generateText('Prompt', [], 'multimodal');

// Vision/Imagem
$resposta = $llm->generateFromImage('Descreva a imagem', [$base64Image]);
```

### Fluxo de Resolução de Modelo

```
LlmService::generateText(prompt, capability='text')
    │
    ▼
resolveModel('text')
    ├── Query llm_models WHERE capability='text' AND is_active=true
    ├── JOIN llm_providers WHERE is_active=true
    ├── ORDER BY provider.priority ASC, model.ranking ASC
    └── Para cada candidato: isWithinQuota()?
        ├── Sim → usa este modelo
        └── Não → tenta o próximo
    │
    ▼
dispatch(model, prompt)
    ├── Google: model no URL via GoogleAiStudioService
    └── Ollama: model no body via OllamaService
    │
    ▼
logUsage() → salva em llm_usage_logs
```

### Provedor ↔ Service Class

Cada provedor no banco tem um `service_class` que mapeia para a classe PHP concreta:

| Provider | service_class | Como passa o model |
|:---------|:-------------|:-------------------|
| Google   | `App\Services\GoogleAiStudioService` | No URL: `/{model}:generateContent` |
| Ollama   | `App\Services\OllamaService` | No body: `{"model": "..."}` |

A API key é lida do `.env` via `api_key_env_var` no registro do provedor (nunca salva no banco).

### `config/llm.php`

Configuração mínima — modelos e provedores vivem no banco de dados:

```php
'default_capability' => 'text',
```

Para popular os dados:

```bash
php artisan db:seed --class=LlmModelsSeeder
```

### `config/googleai.php`

Configuração base do Google AI Studio:

```php
'api_key'   => env('GOOGLEAI_API_KEY'),
'endpoint'  => env('GOOGLEAI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
'model'     => env('GOOGLEAI_MODEL', 'gemini-pro'),  // fallback se não houver modelo no DB
'timeout'   => env('GOOGLEAI_TIMEOUT', 30),
```

### Centralização de Prompts

Todos os prompts estão em `config/prompts.php`, facilitando manutenção sem tocar no código dos serviços.

---

## Geração de PDFs

### Otimização para DomPDF

O layout dos currículos (`base.blade.php`) foi refatorado para evitar o uso de Flexbox, garantindo compatibilidade total com o motor de renderização do `dompdf` e prevenindo problemas de alinhamento/quebra de página.

---

## Envio de Email

Utiliza `EmailService` com Laravel Mail (SMTP).

---

## Currículo Dinâmico

O sistema adapta o currículo para cada vaga usando IA, injetando os dados gerados em um template Blade seguro.

---

## Docker & Infraestrutura

### Otimização para GPU AMD (ROCm) e Modelos Grandes

O ambiente foi configurado para suportar inferência de modelos 32B com aceleração via GPU AMD Radeon (RDNA2+).

**Configurações Importantes (`docker-compose.yml`)**:

- **Memória Compartilhada**: `shm_size: 16gb` (essencial para evitar OOM em modelos grandes).
- **Acesso ao Hardware**: Container roda como `privileged: true` e `user: root` para garantir acesso a `/dev/kfd` e `/dev/dri`.
- **Compatibilidade RDNA2**: Variável `HSA_OVERRIDE_GFX_VERSION=10.3.0` injetada para suporte a GPUs consumer (ex: RX 6600 XT).
- **Fallback Vulkan**: `OLLAMA_VULKAN=1` habilitado como alternativa ao ROCm.

### Serviços Docker

| Container             | Serviço     | Porta       |
| :-------------------- | :---------- | :---------- |
| `consumerIA-php`      | Laravel App | 8888 (Local)|
| `consumerIA-postgres` | PostgreSQL  | 5432        |
| `consumerIA-rabbitmq` | RabbitMQ    | 5672, 15672 |
| `consumerIA-redis`    | Redis       | 6379        |
| `consumerIA-ollama`   | Ollama      | 11434       |

> [!NOTE]
> The application is accessible locally at `http://localhost:8888`.

---

## Privacidade e Segurança

### Auditoria de PII (Personal Identifiable Information)

Realizamos uma varredura completa para garantir que **nenhum dado sensível** seja commitado no repositório.

1.  **Remoção de Hardcoding**: Dados pessoais (nome, telefone, email, endereço) foram removidos de `config/curriculum.php` e `resources/views`.
2.  **`CandidateProfileService`**: Substituiu o antigo arquivo `candidate-profile.json`. O perfil do candidato agora é gerenciado através do banco de dados (tabelas `candidate_*`) e editado através da interface web, com o serviço garantindo acesso seguro a esses dados internamente.
3.  **Schema Relacional de Experiências e Skills**: Refatoramos o armazenamento para utilizar relações robustas:
    - **Unified Skills**: As tabelas `skill_types` e `skills` centralizam todas as tecnologias e competências. `CandidateSkill` e `CandidateExperience` agora referenciam estas tabelas.
    - **Achievements**: A tabela `candidate_achievements` armazena as conquistas de cada experiência de forma relacional ($1:N$).
    - **N:N Technologies**: Experiências agora usam uma tabela pivô `candidate_experience_skill` para vincular tecnologias.
4.  **Sanitização de Logs**: Logs do Laravel não devem conter dados brutos de PII, apenas IDs de referência.

---

## Como Processar Vagas

### Via RabbitMQ (produção)

1. Um sistema externo publica mensagem na fila `process-jobs`.
2. O consumer salva no banco e cria extraction.
3. O scheduler processa as etapas restantes automaticamente.

### Via CLI (desenvolvimento/teste)

```bash
docker exec consumerIA-php php artisan job:process \
  --job-data='{"title":"Dev Laravel","company":"Empresa","link":"https://example.com/vaga","description":"Requisitos..."}'
```
