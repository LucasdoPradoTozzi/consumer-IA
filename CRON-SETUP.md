# Configuração do CRON para Worker RabbitMQ

## Como Funciona

O worker RabbitMQ está configurado para rodar automaticamente via Laravel Scheduler:

- **Frequência**: A cada 1 minuto
- **Timeout**: 50 segundos por execução
- **Proteção**: Usa lock para evitar execuções sobrepostas
- **Background**: Roda em segundo plano sem bloquear o scheduler

## Comandos Disponíveis

### Via Scheduler (Recomendado para Produção)

```bash
# Adicione esta linha no crontab
* * * * * cd /caminho/para/projeto && php artisan schedule:run >> /dev/null 2>&1
```

### Manual (Para Desenvolvimento/Debug)

```bash
# Rodar worker sem timeout (até apertar Ctrl+C)
php artisan worker:consume

# Rodar worker com timeout de 50 segundos
php artisan worker:consume --timeout=50

# Processar apenas uma mensagem e sair
php artisan worker:consume --once
```

## Instalação do Cron no Servidor

### 1. Editar Crontab

```bash
crontab -e
```

### 2. Adicionar a linha do Laravel Scheduler

```bash
* * * * * cd /home/lucasubuntu/Documents/myProjects/consumer-IA && php artisan schedule:run >> /dev/null 2>&1
```

**Ou com Docker:**

```bash
* * * * * docker exec laravelapp-php php artisan schedule:run >> /dev/null 2>&1
```

### 3. Verificar se o cron está rodando

```bash
# Ver crons ativos
crontab -l

# Ver logs do cron (Ubuntu/Debian)
grep CRON /var/log/syslog

# Ver logs do worker
tail -f storage/logs/worker-schedule.log
tail -f storage/logs/laravel.log | grep RabbitMQ
```

## Como Funciona o Controle de Sobreposição

O Laravel Scheduler usa cache/locks para evitar execuções simultâneas:

1. **Minuto 0:00** - Scheduler inicia worker com timeout de 50s
2. **Minuto 0:50** - Worker para automaticamente (timeout)
3. **Minuto 1:00** - Scheduler tenta iniciar novo worker
    - Se o anterior ainda estiver rodando → Aguarda (withoutOverlapping)
    - Se o anterior terminou → Inicia novo worker
4. **Ciclo se repete**

## Monitoramento

### Ver status das filas

```bash
php artisan queue:status
```

### Ver logs do worker em tempo real

```bash
tail -f storage/logs/worker-schedule.log
```

### Verificar se o scheduler está rodando

```bash
# No Laravel
php artisan schedule:list

# Verificar processos
ps aux | grep "artisan worker:consume"
```

## Troubleshooting

### Worker não está processando mensagens

```bash
# 1. Verificar se o cron está configurado
crontab -l

# 2. Verificar logs do scheduler
tail -f storage/logs/worker-schedule.log

# 3. Testar manualmente
php artisan worker:consume --timeout=10

# 4. Verificar conexão com RabbitMQ
php artisan worker:check
```

### Múltiplos workers rodando ao mesmo tempo

```bash
# Verificar processos
ps aux | grep "worker:consume"

# Matar processos antigos
pkill -f "worker:consume"

# Limpar cache de locks
php artisan cache:clear
```

### Lock expirado após 10 minutos

O lock é automaticamente liberado após 10 minutos para evitar locks eternos caso o processo trave.

## Alterando Configurações

### Mudar timeout do worker

Edite `routes/console.php`:

```php
Schedule::command('worker:consume --timeout=120') // 2 minutos
    ->everyMinute()
    ->withoutOverlapping(20) // Aumentar também o lock expiration
```

### Mudar frequência de execução

```php
// A cada 30 segundos
Schedule::command('worker:consume --timeout=25')
    ->everyThirtySeconds()

// A cada 5 minutos
Schedule::command('worker:consume --timeout=240')
    ->everyFiveMinutes()
```

## Produção vs Desenvolvimento

### Desenvolvimento (Local)

```bash
# Rodar manualmente sem timeout
php artisan worker:consume
```

### Produção (Servidor)

```bash
# Deixar o cron gerenciar automaticamente
# Adicionar linha no crontab conforme descrito acima
```

## Logs

- **Worker Log**: `storage/logs/worker-schedule.log` (output do scheduler)
- **Laravel Log**: `storage/logs/laravel.log` (logs detalhados)
- **Cron Log**: `/var/log/syslog` (Ubuntu/Debian) ou `/var/log/cron` (RedHat/CentOS)

## Front-End Monitoring

Acesse: **http://localhost:8888/logs**

Você verá em tempo real:

- Status do worker (ativo/ocioso/inativo)
- Últimas mensagens processadas (Redis)
- Logs do Laravel
- Estatísticas de processamento
