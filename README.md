Explicação técnica

O sistema foi desenvolvido em PHP com Redis para fila e armazenamento dos pagamentos processados. Existem dois serviços PHP-FPM (back1/back2) responsáveis por receber as requisições HTTP de pagamento (/payments) e enfileirar cada transação no Redis.

Um worker PHP (queue) consome essa fila, verifica a saúde dos processadores (default/fallback), encaminha os pagamentos para o processador disponível conforme as regras do desafio, e salva o resultado em estruturas eficientes no Redis.

O load balancer (lb) é implementado em Nginx, distribuindo as requisições entre as instâncias de backend.

Os endpoints expostos são /payments (para submissão) e /payments-summary (para relatório resumido, com suporte a filtros por intervalo de tempo).

Todos os limites de recursos, orquestração dos serviços e persistência foram implementados conforme o regulamento da Rinha de Backend.
