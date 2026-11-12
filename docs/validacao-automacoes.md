# Validação da entrega de automações

Validado localmente em 12/11/2026, usando dados fictícios e sem credenciais de envio.

- Migration aplicada na MariaDB local; painel e novos formulários abriram normalmente.
- Perfil comercial de Rafaela Carvalho salvo, com Curitiba, Pinhais e São José dos Pinhais e atendimento de segunda a sábado conforme disponibilidade. Os envios continuam desligados.
- Evento fictício criou uma conversa e um lead; resposta manual foi enfileirada e cancelada pela interface.
- 554 testes do escopo passaram (2.908 assertions; dois avisos de depreciação herdados). Incluem permissões das rotas, área da cliente, operação, configuração e 11 testes novos de automações.
- Testes novos exercitam duplicidade, autorização da IA, HUMANO, SAIR/ENTRAR, eco do envio, bloqueio de consulta para escrita, configuração concorrente, fila, entrega incerta sem reenvio e invalidação de lembretes por remarcação, quitação e troca de cliente.
- PHPStan passou sem erros; build de frontend concluído. Verificações de escape e scripts inline passaram no Linux.
- Imagem Docker de produção construída e Compose validado. Apache respondeu 403 para `/deploy/Dockerfile`, `/application/.env` e `/.git/HEAD`.
- Webhook sem segredo respondeu 403; rotina de mensagens acessada por HTTP respondeu 404. Rotina CLI executou sem envio por ausência de configuração.
- Backup foi gerado fora da raiz web e restaurado em uma MariaDB 11.4 descartável: 47 tabelas, uma ficha e uma NF conferidas. O banco temporário foi descartado; o banco de trabalho foi preservado.

## Limites da validação

A suíte completa foi executada em Linux sobre os arquivos do checkout Windows: 1.826 testes, com 2 erros, 24 falhas, 1 aviso, 5 depreciações e 1 teste ignorado. Os problemas ficaram fora dos módulos novos, principalmente testes antigos que procuram trechos de código com LF em arquivos CRLF. Também há um teste antigo de cancelamento de OS cujo objeto simulado não implementa `pessoaPermitida`. A suíte completa não está verde; isso precisa de uma frente separada antes de tomar essa execução como certificação de toda a base.

Não foram testados envio real pelo WhatsApp, resposta real da OpenAI, entrega SMTP, DNS/certificado de um domínio real nem publicação em VPS. Esses testes exigem credenciais e infraestrutura do ambiente de destino. O perfil fica no banco de dados, não no código do Git.

## Provedores adicionais de IA

A seleção agora inclui OpenAI, Gemini, DeepSeek e Claude, cada um com sua variável de chave. Foram validados os quatro formatos de requisição e resposta por simulação, além da recusa de resposta vazia e chave ausente. A suíte do escopo passou com 555 testes e 2.942 assertions (dois avisos herdados); os 12 testes de automações passaram isoladamente sem avisos. PHPStan e build passaram. A interface exibiu as quatro opções mantendo as automações desligadas. Não houve chamada real ou cobrança em nenhum provedor; a ativação depende de chave e modelo disponíveis na conta escolhida.
