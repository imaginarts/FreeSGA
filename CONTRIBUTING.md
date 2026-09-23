# Contribuindo com o FreeSGA

Obrigado por querer ajudar! Contribuições são muito bem-vindas — código, documentação, testes, tradução ou apenas reportar um problema.

## Como contribuir

- **Encontrou um bug?** Abra uma [issue](https://github.com/imaginarts/FreeSGA/issues) usando o template de bug.
- **Tem uma ideia?** Abra uma issue de sugestão antes de implementar algo grande, para alinharmos.
- **Quer enviar código?** Faça um fork, crie uma branch e abra um Pull Request (veja abaixo).

## Ambiente de desenvolvimento

Requisitos: **PHP 8.4+**, **Composer**, **Node.js 20+** e **MySQL/MariaDB** (ou PostgreSQL).

```bash
git clone https://github.com/SEU-USUARIO/FreeSGA.git
cd FreeSGA
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed   # cria o usuário admin / 123456
php artisan serve
```

Para o tempo real e as filas durante o desenvolvimento:

```bash
php artisan reverb:start   # WebSocket
php artisan queue:work     # webhooks e avisos
```

## Padrão de código

O projeto segue o estilo padrão do Laravel (via **Pint**). Antes de enviar, formate o código:

```bash
vendor/bin/pint
```

- Escreva o código no mesmo idioma e estilo do que já existe (nomes de banco em inglês/snake_case, interface em português).
- Comente apenas o que não é óbvio; explique o "porquê", não o "o quê".

## Testes

Todo PR precisa passar na suíte de testes, e novas funcionalidades devem vir com testes.

```bash
php artisan test
```

O CI roda automaticamente os testes em cada push e Pull Request.

## Fluxo do Pull Request

1. Faça um fork e crie uma branch a partir da `main`: `git checkout -b minha-melhoria`
2. Faça as alterações, com testes e `vendor/bin/pint` aplicado.
3. Garanta que `php artisan test` passa.
4. Abra o Pull Request descrevendo **o que** mudou e **por quê**.
5. Vincule a issue relacionada, se houver (`Closes #123`).

## Licença

Ao contribuir, você concorda que sua contribuição será distribuída sob a licença [MIT](LICENSE) do projeto.
