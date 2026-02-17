#!/bin/bash

echo "=== VALIDAÇÃO DO CRON DO WORKER ==="
echo ""

echo "1. Verificando crontab configurado:"
echo "-----------------------------------"
crontab -l | grep schedule:run
echo ""

echo "2. Verificando scheduler no Docker:"
echo "------------------------------------"
docker exec laravelapp-php php artisan schedule:list
echo ""

echo "3. Verificando processos do worker:"
echo "------------------------------------"
docker exec laravelapp-php ps aux | grep "worker:consume" | grep -v grep || echo "Nenhum worker rodando no momento"
echo ""

echo "4. Verificando logs do worker (últimas 20 linhas):"
echo "---------------------------------------------------"
docker exec laravelapp-php tail -20 storage/logs/worker-schedule.log 2>/dev/null || echo "Log ainda não foi criado (aguarde o próximo minuto)"
echo ""

echo "5. Verificando últimas mensagens no Redis:"
echo "-------------------------------------------"
docker exec laravelapp-redis redis-cli KEYS "last_message_*" | head -10
echo ""

echo "✅ Para monitorar em tempo real:"
echo "docker exec laravelapp-php tail -f storage/logs/worker-schedule.log"
