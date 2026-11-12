# Fichas, evolução, documentos e retornos

Abra um atendimento e clique em **Ficha de atendimento**. O módulo também aparece como **Fichas e retornos** no menu. Nesta versão, todas as telas, documentos e impressões exigem `cSistema`; ter acesso aos atendimentos ou à área da cliente não dá acesso às fichas.

## Modelos por serviço

Em **Modelos e termos**, há modelos iniciais para amamentação, perfuração de orelha, laserterapia, doula, botox e atendimento geral. Eles começam com perguntas genéricas. A profissional deve definir as perguntas específicas e o texto dos termos que utilizará na sua atividade. Também é possível criar novos modelos.

Use de 1 a 20 perguntas, uma por linha, até 150 caracteres cada. Cada resposta aceita até 2000 caracteres. O termo é opcional e aceita até 12000 caracteres. Alterar um modelo não muda fichas já registradas: título, perguntas, respostas e texto do termo são preservados em cada ficha.

## Ficha e histórico

Escolha um modelo, um serviço que pertença ao atendimento e preencha a ficha. O vínculo com a cliente ou com o bebê vem do atendimento e é conferido novamente ao salvar. A ficha pode ser iniciada mesmo após a quitação.

O histórico identifica a pessoa por cliente e dependente: não mistura a responsável, seus bebês ou outras clientes. O nome registrado na ocasião fica preservado. A ficha inicial não é sobrescrita nem excluída pela interface; correções e complementos entram como novos registros de evolução.

Após criar uma ficha, não é possível trocar a cliente ou o dependente do atendimento, nem excluir esse atendimento. A restrição também vale pela API e no model de atualização. Para outra pessoa, abra outro atendimento.

## Evolução por atendimento ou sessão

Registre data e hora, avaliação, atendimento realizado, orientações e observações ou retificações. Ao menos um texto deve ser preenchido. A data não pode ser futura. Registros mantêm autoria e data de gravação, além da data informada para o atendimento.

O horário da agenda é opcional. Quando selecionado, deve pertencer ao mesmo atendimento, já ter terminado e não estar cancelado; isso inclui sessões de pacotes. Registrar uma evolução não conclui a sessão do pacote, não cria agendamentos e não altera pagamentos. Reenvios do formulário não duplicam a ficha ou a evolução.

## Retornos

Uma evolução pode indicar data de retorno igual ou posterior à data do registro. O menu mostra retornos pendentes até hoje, com filtro por situação e data-limite. A data é uma pendência de acompanhamento, não uma reserva de horário.

Na ficha, conclua ou reabra o retorno com uma observação. O histórico das mudanças mantém data, usuário e observação, sem alterar o conteúdo da evolução. Novos agendamentos continuam sendo feitos na Agenda.

## Fotos, documentos e termos

Envie fotos em JPG/PNG e documentos ou termos em PDF/JPG/PNG. São aplicados os limites de tamanho, MIME e pixels do armazenamento existente. Arquivos ficam em `STORAGE_PATH/fichas`, fora da pasta pública. Fotos têm prévia pela rota autenticada; os demais arquivos são baixados após conferência de permissão e vínculo com a ficha. Caminhos privados não aparecem nas listagens.

O texto do termo presente na criação da ficha pode ser impresso com a identificação da pessoa, responsável, serviço e espaços de assinatura. Use Ctrl+P para imprimir ou salvar em PDF. Depois, anexe o documento assinado indicando nome de quem assinou e data. O sistema registra os dados informados; não coleta assinatura eletrônica, não certifica assinaturas e não fornece um texto jurídico padrão.

Fichas e documentos não são publicados na área da cliente, na API ou na integração WhatsApp. A auditoria registra IDs e operações, sem copiar respostas e conteúdos dos documentos para os logs. Não há exclusão de registros ou anexos nesta interface; retificações devem ser identificadas em novo registro.

## Atualização e backup

Aplique a migration `20261112030000_create_fichas` pelo botão **Atualizar banco de dados**. Faça backup do banco e da pasta de armazenamento protegido, com acesso restrito aos responsáveis pelo sistema. O módulo não configura políticas de retenção ou rotinas de backup automaticamente.
