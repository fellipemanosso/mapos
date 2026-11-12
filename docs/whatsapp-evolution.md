# WhatsApp pela Evolution

Em **Configurações → WhatsApp**, informe a URL HTTPS da Evolution, o nome exato de uma instância existente e sua chave de acesso. Prefira uma chave limitada à instância. O Map-OS não cria nem exclui instâncias.

A configuração não inicia chamadas externas. Depois de salvar, use **Consultar status** para testar. **Conectar / QR Code** solicita a conexão; leia o QR Code em Aparelhos conectados no celular e consulte o status novamente. QR Codes são temporários e não são persistidos no banco ou na sessão.

**Reiniciar conexão** e **Desconectar** exigem confirmação na tela. Desconectar encerra a sessão da instância; pode ser necessário ler outro QR Code. A operação também afeta outros sistemas que compartilham essa instância.

## Credenciais e acesso

- Todas as rotas exigem `cSistema`. Formulários usam POST e CSRF, inclusive a consulta de status.
- A chave é cifrada pela biblioteca Encryption do CodeIgniter usando `APP_ENCRYPTION_KEY`. Não aparece no HTML nem nos logs. Campo vazio preserva a chave apenas no mesmo servidor; mudar a URL exige informá-la novamente.
- Faça backup do banco e da chave de criptografia em local protegido. Sem a chave de criptografia original será necessário cadastrar a credencial da Evolution novamente.
- As chamadas saem do servidor Map-OS, com HTTPS, verificação de certificado, timeout, limite de resposta e sem seguir redirecionamentos. A API precisa estar acessível por esse servidor.
- A URL é definida apenas pelo administrador e pode apontar para uma Evolution em rede privada acessível pelo servidor.

## Compatibilidade

Contrato consultado na Evolution 2.3.7: `GET /instance/connectionState/{instancia}`, `GET /instance/connect/{instancia}`, `POST /instance/restart/{instancia}` e `DELETE /instance/logout/{instancia}`. Versões que alteram esses endpoints precisam de ajuste. Referência: [rotas oficiais](https://github.com/evolution-foundation/evolution-api/blob/2.3.7/src/api/routes/instance.router.ts).

## Limite desta etapa

Esta entrega administra a conexão. Não configura webhooks, não importa mensagens e não envia respostas, lembretes ou mensagens com IA. O indicador de automação desligada se refere ao Map-OS; bots já existentes na Evolution não são alterados. O controle de ligar/pausar será disponibilizado com o processamento de mensagens.

## Atualização

Aplique a migration `20261112010000_add_evolution_config` pelo botão **Atualizar banco de dados** antes de configurar.
