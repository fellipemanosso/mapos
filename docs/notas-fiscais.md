# Notas fiscais dos atendimentos

Abra um atendimento e clique em **Nota fiscal**. Registre se a cliente solicitou NF e anexe o documento emitido externamente, em PDF, JPG ou PNG. Número e data de emissão são opcionais. A data não pode ser futura.

O fluxo funciona inclusive após o atendimento estar faturado ou quitado, sem alterar seus valores ou recebimentos. A emissão continua sendo feita fora do Map-OS; não há integração fiscal.

## Acesso e histórico

- Visualizar e baixar documentos no painel exige `vOs`; solicitar, anexar e alterar a disponibilidade exige `eOs`.
- A cliente autenticada encontra os documentos disponíveis nos detalhes dos próprios atendimentos. Arquivos de outras clientes e documentos retirados não são entregues pela rota do portal.
- **Retirar da área da cliente** preserva o arquivo e o histórico no painel. **Disponibilizar novamente** restaura o acesso. Essas ações não cancelam nem emitem uma NF.
- Atendimentos com histórico de NF não podem ser excluídos, inclusive pela API.
- Os arquivos usam o armazenamento protegido da aplicação, sem expor o caminho interno nas listagens.

## Atualização

Aplique a migration `20261112000000_add_notas_fiscais` pelo botão **Atualizar banco de dados**. Ela adiciona o indicador de solicitação ao atendimento e a tabela de documentos fiscais. Inclua a pasta de armazenamento protegido e o banco nos backups.
