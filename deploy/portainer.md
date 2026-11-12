# Publicação da Rafaela no Portainer

O stack `rafaela` usa Docker Swarm no nó `portainer`, com a rede externa
`manosso` e o Traefik já existente. A aplicação atende em
`https://app.rafaelacarvalho.enf.br/`. O registro DNS é `A app`, apontando para
`213.199.58.135`, com TTL de 300 segundos. O domínio principal e o e-mail
continuam com a configuração anterior da Hostinger.

## Imagem e stack

Construa a imagem a partir de um commit revisado deste repositório e publique
a tag indicada em `portainer-swarm.yaml` no nó que executará os serviços.
O Dockerfile da raiz e `deploy/Dockerfile` são equivalentes. A imagem não
contém o instalador, credenciais nem dependências de desenvolvimento.

No Portainer, crie os secrets externos indicados no YAML antes de implantar
o stack. O secret de ambiente deve conter o `.env` de produção; os secrets
do banco contêm apenas as respectivas senhas, e o secret SQL inicializa uma
base nova com esquema migrado e administrador próprio. Nunca use um dump
de demonstração nem adicione esses valores ao Git.

O ambiente de produção usa HTTPS, cookies seguros, `APP_PROXY_IPS=10.0.1.0/24`,
armazenamento em `/var/lib/mapos/storage` e backups em `/var/backups/mapos`.
Use valores exclusivos para a chave de criptografia, senhas e segredo de
webhook. Configure SMTP, Evolution e as chaves de IA separadamente antes de
habilitar suas automações. A API pública permanece desabilitada por padrão.

Use somente `traefik.swarm.network=manosso`: o Traefik 3.4 rejeita serviços
que misturam os rótulos de rede `traefik.docker.*` e `traefik.swarm.*`.
O roteador usa `websecure` e o resolver `letsencryptresolver`. O banco não
publica portas e permanece apenas na rede interna.

## Persistência e operação

Os volumes `banco`, `storage`, `backups`, `logos`, `perfis` e `logs` pertencem
ao stack. Não os remova durante atualizações. O worker processa as rotinas e
a fila de e-mail a cada minuto. O backup roda a cada seis horas e tenta
novamente em um minuto se o banco ainda não estiver pronto.

Os backups ficam no mesmo servidor; providencie também uma cópia externa
antes de depender deles para recuperação contra perda do host. O SQL de
inicialização só é executado quando o volume do banco está vazio.

Após implantar, confira quatro serviços com uma réplica cada, saúde do app
e do banco, HTTPS confiável, login, calendário e a mensagem de backup
concluído. Atualizações de código exigem nova imagem; mantenha a tag anterior
disponível para retorno e faça backup antes de alterações no esquema.
