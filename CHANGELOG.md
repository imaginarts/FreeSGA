# Changelog

Todas as mudanças notáveis do FreeSGA são documentadas neste arquivo.

O formato segue o [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/),
e o projeto adota o [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [1.0.0] — 2026-09-23

### Adicionado

- **Núcleo de atendimento:** triagem (emissão/impressão de senhas), atendimento
  (chamar, rechamar, iniciar, encerrar, não compareceu, redirecionar), monitor e
  painel de chamadas para TV com som e voz.
- **Fila configurável** por prioridade, pesos, chegada, agendamento e balanceamento,
  com intercalação de prioridades e chamada atômica (dois atendentes nunca chamam a
  mesma senha).
- **Multiunidade** com serviços, contadores, perfis de acesso e lotações.
- **Senha no celular:** QR code na senha abre página pública com posição na fila,
  tempo estimado e aviso de "sua vez".
- **Metas de espera (SLA)** com alertas no monitor e **pausas do atendente**.
- **Impressão térmica direta (ESC/POS)** e **totem de autoatendimento**.
- **Avisos por WhatsApp/SMS** (WhatsApp Cloud API ou HTTP genérico) e **pesquisa de
  satisfação** (NPS/CSAT).
- **LGPD:** mascaramento de dados, auditoria de ações, exportação/anonimização de
  clientes e retenção configurável.
- **Relatórios** e gráficos; **API REST** com autenticação por token (Sanctum);
  **tempo real** com Laravel Reverb; **webhooks** assíncronos.
- Suíte com 105 testes automatizados.

[1.0.0]: https://github.com/imaginarts/FreeSGA/releases/tag/v1.0.0
