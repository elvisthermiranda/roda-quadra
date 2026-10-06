# RodaQuadra

O RodaQuadra é uma aplicação web responsiva para organizar partidas recreativas de vôlei, futsal e futebol. O sistema acompanha a ordem de chegada, forma equipes, controla placar e cronômetro, movimenta a fila de times e mantém o histórico e o ranking de cada encontro.

As regras de negócio e o fluxo completo de operação estão descritos na [documentação funcional](DOCUMENTACAO.md).

## Funcionalidades

- Cadastro e autenticação de organizadores.
- Encontros separados por conta, com suporte a vôlei, futsal e futebol.
- Times configuráveis de 2 a 11 jogadores.
- Cadastro de jogadores com gênero e nível de habilidade.
- Lista de presença ordenada pela chegada.
- Formação automática de times, respeitando primeiro a ordem de chegada e depois o equilíbrio de gênero e habilidade.
- Modos de equipe dinâmico e fixo.
- Ajuste manual de equipes por troca de jogadores.
- Controle da fila e do limite de partidas consecutivas.
- Placar configurável, correção de pontos e confirmação do resultado.
- Cronômetro opcional por partida.
- Registro de saídas, retornos, substituições e empréstimos.
- Confirmação explícita quando a próxima partida terá equipe incompleta.
- Desfazer o último resultado, restaurando escalações e estado da fila.
- Histórico de partidas e ranking por vitórias, aproveitamento e saldo de pontos.
- Placar público, acessível por um token de 40 caracteres, sem expor o painel administrativo.
- Interface mobile-first para uso durante os jogos.

## Tecnologias utilizadas

### Aplicação

| Tecnologia | Versão instalada | Uso |
| --- | --- | --- |
| PHP | 8.4 | Linguagem da aplicação e requisito do ambiente de desenvolvimento/testes |
| Laravel | 13.34 | Rotas, autenticação, validação, banco de dados e regras da aplicação |
| Livewire | 4.4 | Painel e placar reativos, implementados como componentes de arquivo único |
| Livewire Blaze | 1.0 | Otimização da renderização dos componentes Blade |
| TallStackUI | 4.x | Diálogos de confirmação integrados ao Livewire |
| Blade | Laravel 13 | Templates e layouts da interface |
| Tailwind CSS | 4.x | Estilização responsiva |
| Vite Plus | 0.3 | Servidor de desenvolvimento e build dos assets |
| Laravel Vite Plugin | 3.x | Integração dos assets com o Laravel |
| SQLite | desenvolvimento | Banco padrão configurado em `.env.example` |

O Alpine.js necessário para as interações no navegador é fornecido pelo Livewire. O projeto não possui, atualmente, Laravel Reverb instalado.

### Qualidade e desenvolvimento

| Ferramenta | Finalidade |
| --- | --- |
| Pest 5 | Testes unitários e de funcionalidade |
| Larastan 3 | Análise estática de PHP |
| Laravel Pint | Padronização de código PHP |
| Laravel Pail | Visualização de logs em desenvolvimento |
| Laravel Sail | Ambiente Docker opcional |
| Laravel Boost e PAO | Apoio ao desenvolvimento e saída otimizada para agentes |

As versões exatas e reproduzíveis estão nos arquivos `composer.lock` e `package-lock.json`.

## Arquitetura

A aplicação segue a estrutura padrão do Laravel. Os componentes Livewire cuidam da interação e delegam as regras de negócio aos serviços em `app/Services`.

| Serviço | Responsabilidade |
| --- | --- |
| `MeetingService` | Criar, configurar e encerrar encontros |
| `TeamFormationService` | Registrar chegadas e formar times pela ordem de chegada |
| `TeamBalanceService` | Avaliar e melhorar o equilíbrio das equipes |
| `TeamAdjustmentService` | Realizar trocas manuais entre times |
| `MatchService` | Iniciar partidas, controlar placar e cronômetro, confirmar e desfazer resultados |
| `SubstitutionService` | Registrar saídas, preencher vagas e controlar empréstimos |
| `RankingService` | Calcular a classificação do encontro |

Os principais dados persistidos são usuários, jogadores, encontros, participantes, times, partidas e alterações de escalação. Operações sensíveis, como confirmar ou desfazer uma partida, usam transações e bloqueios de banco para manter fila, placar e escalações consistentes.

### Rotas principais

| URL | Acesso | Descrição |
| --- | --- | --- |
| `/` | Público | Página inicial |
| `/register` | Visitante | Criação de conta |
| `/login` | Visitante | Autenticação |
| `/painel` | Autenticado | Operação dos encontros |
| `/placar/{token}` | Público com token | Visualização do placar e da fila |

## Requisitos

- PHP 8.4 ou superior, com as extensões exigidas pelo Laravel e pelo driver do banco escolhido.
- Composer 2.
- Node.js 22 ou 24 e npm.
- SQLite para a instalação local padrão, ou PostgreSQL/MySQL em ambientes persistentes.

> O `composer.json` aceita PHP 8.3 na aplicação, mas as dependências de desenvolvimento atuais, em especial o Pest 5, exigem PHP 8.4. Use PHP 8.4 para instalar o projeto completo.

## Instalação local

Clone o repositório e entre na pasta do projeto:

```bash
git clone <URL_DO_REPOSITORIO>
cd queue-volei
```

O projeto possui um script que instala as dependências, cria o `.env`, gera a chave, executa as migrations e compila os assets. Como a configuração padrão usa SQLite, crie primeiro o arquivo do banco:

```bash
touch database/database.sqlite
composer setup
```

Inicie o ambiente de desenvolvimento:

```bash
composer dev
```

A aplicação ficará disponível, normalmente, em `http://localhost:8000`. O comando de desenvolvimento inicia os processos definidos pelo Laravel para servir a aplicação e os assets.

### Instalação manual

Se preferir executar cada etapa separadamente:

```bash
composer install
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

Em outro terminal, use `npm run dev` para recarregamento automático dos assets durante o desenvolvimento.

## Configuração

As configurações ficam no arquivo `.env`. Nunca envie esse arquivo ou chaves reais para o repositório.

Variáveis mais importantes:

```dotenv
APP_NAME=RodaQuadra
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Para usar PostgreSQL localmente:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rodaquadra
DB_USERNAME=postgres
DB_PASSWORD=senha
```

Depois de alterar a conexão, execute:

```bash
php artisan config:clear
php artisan migrate
```

Sessões, cache e filas estão configurados para usar o banco de dados. As tabelas necessárias já fazem parte das migrations. Não há jobs assíncronos próprios no código atual, portanto um worker não é necessário para as funcionalidades existentes; ele passa a ser necessário quando jobs forem adicionados.

## Banco de dados e dados de exemplo

Para recriar o banco local e executar o seeder:

```bash
php artisan migrate:fresh --seed
```

Esse comando apaga os dados do banco configurado. Use-o somente em desenvolvimento.

Para vincular encontros e jogadores antigos, sem proprietário, a uma conta já cadastrada:

```bash
php artisan meetings:claim-legacy usuario@exemplo.com
```

## Testes e qualidade

Execute toda a verificação do projeto — formatação, análise estática e testes — com:

```bash
composer test
```

Comandos individuais:

```bash
php artisan test --compact
composer types:check
composer lint:check
composer lint
npm run build
```

Os testes de funcionalidade cobrem isolamento entre contas, gerenciamento de jogadores, formação e equilíbrio dos times, fluxo das partidas, placar público, configurações do encontro e substituições.

## Build para produção

Antes de publicar uma versão, instale dependências reproduzíveis e gere os assets:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan optimize
```

O servidor web deve apontar seu document root para a pasta `public`, nunca para a raiz do repositório. As pastas `storage` e `bootstrap/cache` precisam ser graváveis pelo usuário do PHP.

Use estas configurações em produção:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
LOG_CHANNEL=stderr
```

Gere `APP_KEY` uma única vez e preserve a mesma chave entre deploys. Trocar a chave invalida cookies e pode tornar dados criptografados ilegíveis.

## Deploy no Laravel Cloud

O Laravel Cloud é o caminho recomendado para esta aplicação. O deploy parte de um repositório Git no GitHub, GitLab ou Bitbucket.

1. Envie o projeto para um repositório Git.
2. No Laravel Cloud, crie a aplicação a partir desse repositório e escolha PHP 8.4.
3. Crie e conecte um banco PostgreSQL ou MySQL ao ambiente. SQLite não é suportado no Laravel Cloud porque o filesystem das instâncias é efêmero.
4. Configure as variáveis `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL`. A plataforma injeta as credenciais dos recursos conectados; não duplique as variáveis de banco sem necessidade.
5. Use o seguinte comando de build:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction && npm ci && npm run build && php artisan optimize
   ```

6. Use o seguinte comando de deploy:

   ```bash
   php artisan migrate --force
   ```

7. Faça o primeiro deploy e valide cadastro, login, criação de encontro, painel e placar público.

O Cloud faz deploy com troca de release e sem interrupção quando build e migrations terminam com sucesso. Não execute `storage:link`, `optimize:clear`, `queue:restart` ou `horizon:terminate` no comando de deploy do Cloud.

### Deploy pelo Cloud CLI

O CLI é opcional. Para instalá-lo apenas no ambiente de desenvolvimento:

```bash
composer require --dev laravel/cloud-cli
./vendor/bin/cloud auth -n
./vendor/bin/cloud ship -h
```

Consulte primeiro a ajuda de `ship`, pois os argumentos podem variar. Em deploys posteriores:

```bash
./vendor/bin/cloud deploy NOME_DA_APLICACAO production -n
./vendor/bin/cloud deploy:monitor -n
```

Sempre monitore o deploy até a conclusão. A instalação do CLI altera as dependências do projeto e, por isso, deve ser feita conscientemente e versionada apenas se a equipe decidir adotá-lo.

### Recursos de produção

- **Banco:** conecte PostgreSQL ou MySQL para persistir usuários, encontros, sessões, cache e a fila configurada no banco.
- **Arquivos:** a aplicação atual não mantém uploads. Se isso mudar, use Object Storage; arquivos locais não sobrevivem entre releases ou réplicas do Cloud.
- **Filas:** não é necessário criar worker enquanto não houver jobs assíncronos. Ao adicioná-los, use Managed Queues ou um Worker e monitore `failed_jobs`.
- **Agendador:** não há tarefas próprias agendadas hoje. Quando forem adicionadas, habilite o scheduler do ambiente.
- **Domínio:** associe o domínio, configure os registros DNS solicitados e aguarde a emissão automática do certificado TLS.

## Deploy em VPS ou hospedagem própria

Em uma VPS, configure Nginx ou Apache com PHP-FPM 8.4 e document root em `public`. O fluxo básico de cada release é:

```bash
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Se futuramente a aplicação passar a processar jobs, mantenha `php artisan queue:work` sob Supervisor ou outro gerenciador de processos e execute `php artisan queue:restart` após o deploy. Para tarefas agendadas, configure o cron para chamar `php artisan schedule:run` a cada minuto.

Antes do primeiro acesso, confirme as permissões de escrita:

```bash
chmod -R ug+rwX storage bootstrap/cache
```

O usuário e o grupo exatos devem corresponder ao processo PHP do servidor. Não use permissões globais como `777`.

## Estrutura do projeto

```text
app/
├── Console/Commands/       Comandos Artisan próprios
├── Http/Controllers/Auth/  Cadastro, login e logout
├── Models/                 Modelos Eloquent
└── Services/               Regras de negócio
config/                     Configurações do Laravel e do domínio
database/
├── factories/              Fábricas para testes
├── migrations/             Estrutura do banco
└── seeders/                Dados de exemplo
resources/
├── css/                    Tailwind CSS
├── js/                     Entrada JavaScript
└── views/                  Blade e componentes Livewire
routes/                     Rotas web e comandos de console
tests/                      Testes Pest unitários e de funcionalidade
```

## Licença

Este projeto declara a licença MIT em seu `composer.json`.
