# FreeSGA — Sistema de Gerenciamento de Atendimento

Sistema **livre e gratuito** de senhas e filas de atendimento em **Laravel 13 + Livewire 4 + Reverb**. Open-source sob licença MIT, inspirado no [NovoSGA](https://github.com/novosga/novosga).

🌐 **Site:** https://imaginarts.github.io/FreeSGA/

## Módulos

| Módulo | Rota | O que faz |
|---|---|---|
| Triagem | `/triagem` | Emite e imprime senhas (normal/prioridade), identifica cliente, confirma agendamentos |
| Atendimento | `/atendimento` | Chamar próxima, rechamar, iniciar, encerrar (serviços realizados), não compareceu, redirecionar |
| Monitor | `/monitor` | Filas da unidade em tempo real, transferir, cancelar e reativar senhas |
| Painéis | `/paineis` | Cadastro dos painéis de TV; painel público em `/painel/{uuid}` com som e voz |
| Relatórios | `/relatorios` | Gráficos e 9 relatórios para impressão |
| Agendamentos | `/agendamentos` | Agenda por unidade/serviço; a chegada é confirmada na triagem |
| Clientes | `/clientes` | Cadastro e histórico de senhas |
| Usuários | `/usuarios` | Usuários e lotações (unidade + perfil) |
| Configurações | `/configuracoes` | Serviços da unidade (sigla, contador, tipo), impressão, atendentes e seus serviços |
| Administração | `/admin` | Unidades, serviços, prioridades, locais, departamentos, perfis, webhooks, tokens de API, comportamento e ordenação da fila |

## Senha no celular e tempo estimado

Configurável por unidade em **Configurações da unidade → Senha no celular** (cada item pode ser ligado ou desligado):

- **Senha no celular:** QR code impresso na senha abre `/s/{id}/{token}`, uma página pública que atualiza em tempo real.
  - Posição na fila
  - Tempo estimado de espera
  - Aviso de "sua vez está chegando" (som, vibração e notificação) quando faltarem N senhas
  - QR code também na tela da triagem
- **Tempo estimado na triagem:** espera prevista em cada serviço.
- **Tempo estimado no painel da TV:** opção em cada painel (módulo Painéis).

A estimativa usa o ritmo real de chamadas do serviço na janela configurada (padrão 60 min). Sem chamadas recentes, usa a duração média dos atendimentos dos últimos 7 dias dividida pelos atendentes ativos.

## Metas de espera e pausas

Configurável por unidade em **Configurações da unidade → Metas e pausas**:

- **Metas de espera (SLA):** meta padrão da unidade e meta própria por serviço (Serviços → editar).
  - No monitor, senhas ficam amarelas perto da meta e vermelhas acima dela. Há indicadores de senhas acima da meta, maior espera e % dentro da meta no dia.
  - Alerta sonoro no monitor (opcional).
  - Destaque na fila do atendente (opcional).
  - Relatório "Cumprimento de metas de espera".
- **Pausas do atendente:** motivos em **Admin → Motivos de pausa**, com tempo máximo opcional.
  - O atendente pausa e retoma pela tela de Atendimento; em pausa não chama senhas.
  - O monitor mostra cada atendente como atendendo, em pausa ou disponível, destacando pausas acima do limite.
  - Motivo obrigatório: opcional.
  - Relatório "Pausas por atendente".
  - A rotina diária encerra pausas esquecidas há mais de 12 h.

## Impressoras térmicas e totens

Configurável por unidade em **Configurações da unidade → Impressoras e totens**:

- **Impressão direta (ESC/POS):** cadastre impressoras de rede (IP:9100) ou compartilhadas no Windows do servidor (USB).
  - O servidor imprime sem janela de impressão.
  - Opções por impressora: papel 58/80 mm, corte, QR code, remover acentos, e botão de página de teste.
  - Na **Triagem**, cada estação escolhe a sua impressora; a escolha fica gravada no navegador.
- **Totem de autoatendimento:** link público `/totem/{uuid}` para tela de toque. Opções por totem:
  - Serviços exibidos
  - Botão preferencial
  - CPF (opcional ou obrigatório, com validação)
  - Check-in de agendamento por CPF
  - Espera estimada e QR de acompanhamento
  - Alto contraste e cor
  - Tempos de retorno à tela inicial
  - Impressão pelo navegador, pela impressora térmica ou nenhuma
- No computador do totem, use `chrome.exe --kiosk --kiosk-printing "URL"` para tela cheia e impressão sem janela.

## Avisos por WhatsApp/SMS e pesquisa de satisfação

- **Provedor** (Admin → WhatsApp / SMS):
  - WhatsApp Cloud API (oficial da Meta, com modelos aprovados)
  - HTTP genérico (Z-API, Evolution API, Twilio, SMS…)
  - Modo teste, que só registra no log
  - Textos editáveis com variáveis `{senha} {servico} {unidade} {local} {posicao} {link}`
  - Envio de teste e histórico dos últimos envios
  - Token e cabeçalhos ficam criptografados
- **Avisos por unidade** (Configurações da unidade → Avisos e pesquisa):
  - Confirmação da senha com link de acompanhamento
  - "Faltam N senhas"
  - "Senha chamada, dirija-se ao Guichê X"
  - O telefone é informado na triagem, no totem (opção por totem) ou pela API (`notify_phone`). É apagado quando as senhas são arquivadas.
- **Pesquisa de satisfação:**
  - NPS (0 a 10) ou satisfação (1 a 5), com comentário opcional
  - Link enviado por WhatsApp/SMS ao encerrar e/ou exibido na página de acompanhamento
  - Uma resposta por atendimento, válida por 7 dias
  - Relatório "Pesquisa de satisfação" por atendente e serviço
- Os envios usam a fila: mantenha `php artisan queue:work` rodando.

## LGPD e auditoria

Em **Admin → Privacidade e auditoria**:

- **Registro de ações:**
  - Sempre registradas:
    - Ações sensíveis em senhas: cancelar, reativar, transferir, redirecionar, não compareceu
    - Alterações de cadastros e configurações, com antes e depois
    - Rotinas de dados
  - Opcionais:
    - Fluxo completo das senhas
    - Logins e falhas de login
    - Acesso a dados pessoais
  - Consulta com filtros por ação, usuário e período, e exportação em CSV
  - Senhas, tokens e cabeçalhos nunca são gravados. De clientes, registra só quais campos mudaram.
- **Privacidade:**
  - Nome do cliente no painel/voz: oculto, primeiro nome ou completo. O documento nunca vai para o painel.
  - CPF mascarado nas telas de operação (`***.982.247-**`)
  - Nome do atendente nas páginas públicas
  - Texto de consentimento na coleta do telefone
- **Direitos do titular** (módulo Clientes): exportar dados (JSON) e anonimizar (irreversível, mantém as estatísticas).
- **Retenção** (`php artisan sga:privacy-cleanup`, diário às 03:00):
  - Anonimização de clientes inativos há N meses (opcional)
  - Histórico de mensagens apagado após 90 dias
  - Auditoria apagada após N dias
  - Telefones de aviso apagados no reinício diário das senhas

## Instalação

```bash
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate   # configure DB_* e REVERB_*
php artisan migrate --seed
```

Usuário inicial: **admin / 123456** (troque a senha em "Meu perfil").

## Executando

```bash
php artisan serve          # aplicação
php artisan reverb:start   # WebSocket (tempo real)
php artisan queue:work     # webhooks
php artisan schedule:work  # rotina diária (reinício de senhas 00:01)
```

Sem o Reverb o sistema continua funcionando: as telas fazem polling a cada 8–30 s.

## Regras principais

- **Numeração** por serviço na unidade (`unit_services.next_number`), com lock de linha, incremento, número final (volta ao início) e limite de senhas.
- **Ordem da fila** configurável (agendamento, prioridade, pesos, chegada, balanceamento) e **intercalação** de prioridades.
- **Chamada atômica**: dois atendentes nunca chamam a mesma senha.
- **Reinício diário** (`php artisan sga:reset`): arquiva as senhas (continuam nos relatórios), limpa os painéis e zera os contadores.
- **Webhooks** assíncronos para `ticket.created`, `ticket.called`, `ticket.finished` etc.

## API

Tokens em **Admin → API**. Autenticação `Authorization: Bearer <token>`.

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/v1/tickets` | Emite senha (`unit_id`, `service_id`, `priority_id`, `customer[name,document]`) |
| GET | `/api/v1/tickets/{id}` | Consulta senha |
| POST | `/api/v1/tickets/{id}/{acao}` | `call`, `recall`, `start`, `finish`, `no-show`, `redirect`, `cancel`, `reactivate`, `transfer` |
| POST | `/api/v1/units/{id}/call-next` | Chama a próxima da fila do usuário do token |
| GET | `/api/v1/units/{id}/queue` | Fila da unidade |
| GET | `/api/v1/units/{id}/services` | Serviços ativos |
| GET | `/api/v1/units/{id}/panel` | Últimas chamadas |
| GET | `/api/v1/priorities` | Prioridades |

Impressão pública da senha: `/senha/{id}/imprimir?hash={hash}` (o `hash` vem na resposta da API).

## Testes

```bash
php artisan test
```
