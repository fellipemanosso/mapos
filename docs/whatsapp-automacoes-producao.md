# WhatsApp, assistente comercial e instalação em produção

## O que está implementado

- Caixa de conversas individuais com busca, histórico paginado, lead vinculado, resposta manual e cancelamento de envios pendentes.
- Webhook Evolution v2 com autenticação por cabeçalho, limite de corpo, validação da instância e deduplicação dos eventos. Grupos, status e identificadores LID são ignorados. Áudios, fotos e outros conteúdos sem texto aparecem como aviso para consultar o WhatsApp, sem baixar arquivos.
- Pausa da IA quando a profissional responde pelo celular ou pelo sistema, quando a cliente pede HUMANO ou apresenta um assunto clínico detectado. O atendimento humano continua pelo próprio WhatsApp ou pela caixa de conversas.
- IA comercial com seleção entre OpenAI, Google Gemini, DeepSeek e Claude, sem ferramentas, sem acesso às fichas, documentos, finanças ou agenda. A disponibilidade e o preço final precisam ser confirmados pela profissional.
- Confirmação 48h antes, lembrete 24h antes, aviso semanal de saldo vencido e retorno no dia previsto. Cada tipo é ativado separadamente e depende de uma cliente vinculada e autorização registrada na conversa.
- Rotinas CLI para mensagens, diagnóstico e backup diário do banco e armazenamento privado.
- Instalação Docker com Apache/PHP, MariaDB, Caddy com HTTPS, processamento de mensagens e e-mails e backup periódico.

Nada é ativado pela migration. Os dados reais da profissional, credenciais, domínio, SMTP e instância precisam ser configurados na instalação de destino.

## Atualização e permissões

Aplique `20261112050000_add_automacoes` em Configurações → Atualizar banco. A migration cria três tabelas e habilita somente o grupo Administrador (ID 1):

| Permissão | Acesso |
| --- | --- |
| `vWhatsapp` | Consultar conversas e conteúdo das mensagens |
| `eWhatsapp` | Responder, assumir, vincular cliente e registrar autorização de lembretes |
| `cSistema` | Configurar perfil comercial, modelos e automações |

`eWhatsapp` implica consulta. Vincular outra cliente exige também `vCliente`. O link do lead só aparece com `vOs`. A conversa não recebe acesso às fichas clínicas. Mensagens recebidas podem conter dados privados: conceda consulta apenas a quem atende. Mudanças de controle e envios manuais são auditados sem copiar o texto para a auditoria.

## Evolution existente

1. Em Configurações → WhatsApp, salve a URL HTTPS, instância e chave. Consulte o status. O número existente continua na mesma instância; não crie outra conta ou número.
2. Gere um segredo exclusivo (por exemplo `php -r "echo bin2hex(random_bytes(32));"`) e configure `EVOLUTION_WEBHOOK_SECRET` no `application/.env` do servidor. Não use a chave da Evolution como segredo de webhook.
3. Na instância Evolution, configure o webhook para a URL mostrada em Perfil e automações. Ative apenas `MESSAGES_UPSERT`, `enabled: true`, `byEvents: false`, `base64: false`. Em `headers`, informe `X-Mapos-Webhook` com o mesmo segredo. Não coloque o segredo na URL.
4. Não substitua inadvertidamente um webhook usado por outra aplicação. Se a instância já usa outro destino, decida qual sistema recebe os eventos ou use um encaminhador autenticado. Desative bots concorrentes antes de ativar a IA deste sistema.
5. Envie uma mensagem de um número de teste. Ela deve aparecer em Conversas WhatsApp e criar um único lead. O vínculo com uma cliente existente é conferido pela profissional: nunca é inferido só pelo nome ou telefone.

O webhook aceita POST e exige o segredo mesmo sem login. Somente suas duas URLs exatas ficam fora do CSRF; todas as telas do painel continuam exigindo sessão, permissão e CSRF. Não usa o campo `apikey` do corpo e não armazena o evento bruto.

O formato usado foi conferido nas fontes da Evolution: [DTO de envio de texto](https://github.com/EvolutionAPI/evolution-api/blob/main/src/api/dto/sendMessage.dto.ts) e [webhooks e cabeçalhos](https://github.com/EvolutionAPI/evolution-api/blob/main/src/api/integrations/event/webhook/webhook.controller.ts). A instalação real ainda deve passar por teste de compatibilidade com a versão que você utiliza.

## Perfil, mensagens e autorizações

Em Perfil e automações, preencha nome, apresentação, serviços, horários, cidades/bairros e condições comerciais. Defina janela de envio e limite diário. Modelos aceitam as variáveis indicadas na tela. Nome do sistema, tema e dados do MEI/logotipo reutilizam as configurações existentes, com links na mesma tela.

Comandos recebidos:

| Texto exato ou intenção | Efeito |
| --- | --- |
| `AUTORIZO IA` | Registra autorização e permite atendimento por IA, se habilitado globalmente |
| `HUMANO`, pedido de profissional ou assunto clínico detectado | Pausa a IA; atendimento humano |
| `AUTORIZO LEMBRETES` | Registra autorização de mensagens; ainda exige vínculo com cliente para lembretes |
| `CONFIRMAR` | Destaca a resposta como confirmação da cliente e encaminha à profissional, sem alterar um horário automaticamente |
| `SAIR` | Bloqueia novos envios, cancela pendências e remove autorizações |
| `ENTRAR` | Reabre mensagens em modo humano, sem reativar autorizações anteriores |

Na conversa, a profissional pode registrar autorização obtida por outro meio e vincular o código da cliente responsável. Confira o número e o cadastro antes de marcar essa autorização. A interrupção por SAIR só é removida pela própria cliente. Vincular uma cliente não libera dados do bebê para IA nem autoriza mensagens por si só.

## IA comercial

Selecione o provedor em Perfil e automações e informe um modelo disponível na respectiva conta. Ao trocar de provedor, confira também o identificador do modelo. Configure a chave correspondente exclusivamente no servidor:

| Provedor | Variável de ambiente |
| --- | --- |
| OpenAI | `OPENAI_API_KEY` |
| Google Gemini | `GEMINI_API_KEY` |
| DeepSeek | `DEEPSEEK_API_KEY` |
| Claude / Anthropic | `ANTHROPIC_API_KEY` |

Somente o provedor selecionado recebe a solicitação; não existe fallback automático nem reaproveitamento da chave de outro provedor. Configurações antigas sem provedor explícito continuam usando OpenAI; não há nova migration para esta seleção. A IA permanece desligada enquanto a chave e o modelo não forem configurados. A cliente precisa escrever AUTORIZO IA; o texto de boas-vindas explica a opção e o atendimento humano.

Quando OpenAI é selecionada, a chamada usa [Responses API](https://developers.openai.com/api/reference/resources/responses/methods/create), `store: false` e limite de saída. Isso desativa o armazenamento da resposta pela API, sem equivaler a uma garantia de retenção zero em todos os serviços do provedor. As outras opções usam as APIs oficiais [Gemini GenerateContent](https://ai.google.dev/api/generate-content), [DeepSeek Chat Completions](https://api-docs.deepseek.com/api/create-chat-completion/) e [Claude Messages](https://platform.claude.com/docs/en/api/messages/create). Claude usa a versão `2026-10-21`. As políticas de retenção dos demais provedores dependem da conta e do contrato de cada serviço; `store: false` é específico da chamada OpenAI implementada aqui. Respostas truncadas, bloqueadas ou vazias são recusadas e levam ao atendimento humano.

São enviados o texto comercial da solicitação e a base comercial configurada, com e-mails e números longos substituídos. A versão inicial responde a cada solicitação sem enviar histórico clínico ou anexos; ela não executa ações no sistema.

O filtro de encaminhamento clínico é uma barreira adicional, não uma classificação clínica. Informações privadas podem ser digitadas pela própria cliente em uma mensagem: revise as políticas de uso e mantenha a opção HUMANO acessível. Falha da IA ou resposta fora de escopo leva a conversa ao atendimento humano; não existe tentativa infinita ou execução de instruções recebidas nas mensagens.

## Fila e lembretes

Rode a cada minuto, a partir da raiz do projeto:

```sh
php index.php rotinas processar
php index.php email process
```

Esses comandos respondem 404 por HTTP. Um bloqueio em arquivo impede workers simultâneos no mesmo armazenamento. Compartilhe o mesmo storage entre as réplicas. O limite diário inclui envios confirmados e entregas incertas. A janela de envio usa `APP_TIMEZONE` (padrão São Paulo).

As janelas de geração são 46–48h e 22–24h antes do atendimento; um servidor parado não dispara lembretes antigos em massa. Saldo vencido gera no máximo um aviso por semana, valor e vencimento; retornos usam a data pendente da evolução, com texto genérico sem detalhes de saúde. Cancelamento, remarcação, quitação, troca de vínculo, opt-out e desligamento da automação são conferidos de novo antes do envio. Mensagens pendentes há mais de 24h são canceladas quando processadas.

A fila persiste o estado antes da requisição externa. Timeouts ou interrupções ficam como **incerto** e não são reenviados automaticamente. Confira no WhatsApp antes de preparar uma nova resposta. Uma mensagem já em transmissão pode concluir mesmo se alguém pausar a conversa no mesmo instante. O envio manual também entra na fila e respeita janela e limite diário.

## Backup

Crie um diretório privado fora da raiz web, configure `BACKUP_PATH` e execute:

```sh
php index.php rotinas backup
```

A rotina produz um ZIP por dia com `banco.sql`, manifesto, arquivos de `STORAGE_PATH` e as imagens públicas de `assets/uploads` e `assets/userImage`. Exclui sessões, tentativas de login, tokens temporários e recuperação de senha. Retenção configurável por `BACKUP_RETENTION_DAYS` (7–365, padrão 30). Não inclui `.env` nem chave de criptografia: guarde as credenciais separadamente. O ZIP contém dados privados e não é cifrado pelo aplicativo; use armazenamento restrito e cópia externa cifrada.

Para restaurar, pare app/worker, restaure o SQL em um banco vazio, extraia `storage/` para o `STORAGE_PATH` com permissão do serviço, restaure `public/assets/` nas pastas correspondentes e recoloque o `.env` com a mesma `APP_ENCRYPTION_KEY`. Confira fichas, NF, pagamentos e um arquivo antes de retomar os envios. Um backup só deve ser considerado recuperável depois desse teste.

## Produção com Docker

Os arquivos em `deploy/` são separados da configuração de desenvolvimento. Antes de publicar:

1. Prepare uma VPS com Docker Compose, DNS do domínio apontando para ela e portas 80/443 disponíveis.
2. Use `deploy/compose.env.example` como referência para um arquivo privado **fora do repositório**, com domínio e senhas diferentes para aplicação e root do banco.
3. Prepare `application/.env` com os dados reais. Para esse Compose: `DB_HOSTNAME=db`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` iguais aos do arquivo privado; `APP_BASEURL=https://seu-dominio/`; `APP_ENVIRONMENT=production`; `STORAGE_PATH=/var/lib/mapos/storage`; `BACKUP_PATH=/var/backups/mapos`; `APP_PROXY_IPS=172.28.50.2`; `APP_COOKIE_SECURE=true`. Preserve CSRF e configure uma chave de criptografia forte. Confira que a sub-rede reservada não conflita com sua infraestrutura.
4. Construa e suba banco e app primeiro. Restaure o banco personalizado e seus anexos; não use dados de demonstração em produção. O instalador grava o `.env`, que neste Compose é montado somente para leitura: este fluxo é para restaurar a instalação já configurada.

```sh
docker compose --env-file /caminho/privado/mapos-compose.env -f deploy/compose.yaml build app
docker compose --env-file /caminho/privado/mapos-compose.env -f deploy/compose.yaml up -d db app proxy
# Restaure banco, storage e logos, depois abra o painel e aplique migrations pendentes.
docker compose --env-file /caminho/privado/mapos-compose.env -f deploy/compose.yaml exec --user www-data app php index.php rotinas diagnostico
# Só então ative as rotinas e as automações desejadas.
docker compose --env-file /caminho/privado/mapos-compose.env -f deploy/compose.yaml up -d worker backup
```

Caddy solicita e renova o certificado pelo domínio. Banco e Apache não publicam portas no host. Volumes preservam banco, arquivos, logotipos, logs e certificados. O serviço de backup verifica a cópia diária a cada seis horas. Não execute `down -v` em uma instalação com dados que precisa preservar. Mantenha uma cópia externa dos volumes e do `.env`.

Para e-mail, configure os campos `EMAIL_SMTP_*` existentes no `.env`, remetente nos dados do emitente e valide uma mensagem para um destinatário autorizado. A fila de e-mails é a já existente no Map-OS; as novas automações de lembretes deste módulo usam WhatsApp. O diagnóstico informa configuração, não garante entrega SMTP ou aprovação de DNS.

## Critérios de ativação real

- Login e área da cliente funcionando em HTTPS, cookies seguros e isolamento dos clientes conferido.
- Evolution conectada e teste de recebimento, resposta manual, eco do envio, HUMANO, SAIR e reconexão.
- Perfil, termos, condições e modelos revisados pela profissional.
- Teste de IA com número autorizado e limite baixo; nenhuma alteração automática de agenda ou financeiro.
- Worker recente, lembretes com dados fictícios, teste de quitação/cancelamento e entrega incerta.
- E-mail entregue, backup copiado para fora da VPS e restauração testada.

Sem domínio, servidor e credenciais reais, esses critérios permanecem pendentes; o código e a configuração podem ser preparados e testados localmente.
