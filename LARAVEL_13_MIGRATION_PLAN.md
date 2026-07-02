# Plano de Migração — Suporte ao Laravel 13

> Documento de planejamento para o fork `ilsondev/voyager`. Base de partida: branch `1.8` (já com suporte a Laravel 11). Objetivo: suportar oficialmente o **Laravel 13**.

## 1. Contexto

- Laravel 13 foi lançado em **17/03/2026**, com PHP **8.3 como mínimo** (PHP 8.1/8.2 removidos), suporte até PHP 8.5.
- É descrito pela própria equipe do Laravel como um release de baixo impacto para a maioria das aplicações ("~10 minutos" de upgrade), mas o Voyager **não é uma aplicação comum** — é um pacote que ainda depende de uma peça já removida do Laravel há duas majors (Doctrine DBAL), então o esforço real de migração é maior do que o guia oficial sugere.
- Fonte oficial: [Laravel 13.x Upgrade Guide](https://laravel.com/docs/13.x/upgrade).

## 2. Estado atual do projeto (levantamento)

| Área | Situação | Arquivo(s) |
|---|---|---|
| Constraint do Laravel | `"illuminate/support": "11.*"` (hardcoded) | `composer.json:23` |
| PHP mínimo | `^8.2\|^8.3` | `composer.json:22` |
| Doctrine DBAL | Ainda em uso ativo em ~59 arquivos, incluindo chamada direta a `getDoctrineSchemaManager()` (método que **não existe mais** desde o Laravel 11) | `src/Database/Schema/SchemaManager.php:156`, `src/Database/Types/**` |
| Testes | `TestCase` estende `Orchestra\Testbench\BrowserKit\TestCase` — pacote legado/abandonado | `tests/TestCase.php:6` |
| CI | Matriz fixa em PHP 8.1–8.3 / Laravel `11.*`, instala o Laravel via `composer require illuminate/support:${{ matrix.laravel }}` | `.github/workflows/test.yml`, `.github/workflows/coverage.yml` |
| Frontend | Laravel Mix 6 (descontinuado), Vue 2.7 (EOL), Bootstrap 3.4 | `package.json`, `webpack.mix.js` |
| Dependências de terceiros | `intervention/image ^2.7` (v3 existe com breaking changes), `laravel/browser-kit-testing`, `orchestra/testbench-browser-kit`, `arrilot/laravel-widgets ^3.7`, `laravel/ui >=1.0`, `league/flysystem ~1.1\|~2.0\|~3.0` | `composer.json` |
| Roteamento | Sintaxe de array antiga (`['uses' => ..., 'as' => ...]`) — ainda suportada pelo Laravel, não é um bloqueador, mas é boa oportunidade de modernização | `routes/voyager.php` |
| Service Provider | `AliasLoader::getInstance()`, `call_user_func()` para hooks de model — funcional, não é bloqueador | `src/VoyagerServiceProvider.php:62, 99-105` |
| Paginação Bootstrap | Usa `Paginator::useBootstrap()` nativo do Laravel (guardado por `method_exists`) — não referencia diretamente as views `pagination::default`/`pagination::bootstrap-3`, então a troca de nome interno do Laravel 13 não deve quebrar nada, mas precisa ser **validado em teste manual** | `src/VoyagerServiceProvider.php:132-134` |

## 3. Principais mudanças do Laravel 13 relevantes para o Voyager

Fonte: [laravel.com/docs/13.x/upgrade](https://laravel.com/docs/13.x/upgrade)

- **Alto impacto**: `laravel/framework` deve ir para `^13.0`; `phpunit/phpunit` para `^12.0` (ou Pest `^4.0`); CSRF middleware renomeado de `VerifyCsrfToken` para `PreventRequestForgery` (Voyager não referencia essa classe diretamente — sem impacto direto, mas relevante se algum consumidor do pacote excluir o middleware antigo).
- **Médio impacto**: `cache.serializable_classes` agora restringe deserialização de objetos por padrão; `upsert()` no MySQL/MariaDB agora exige `uniqueBy` não vazio.
- **Baixo impacto, mas relevantes ao Voyager**:
  - Nomes internos das views de paginação Bootstrap mudaram (`pagination::default` → `pagination::bootstrap-3`) — Voyager usa a API nativa, deve estar protegido, mas requer teste manual da paginação no admin.
  - `Container::call` agora respeita defaults nulos de classes não vinculadas — pode afetar resolução de dependências em métodos como os `Handle`/`Actions` do Voyager que usam injeção de método.
  - Modelos não podem mais ser instanciados durante o próprio boot (`LogicException`) — vale auditar os observers/boot hooks do Voyager.
  - `symfony/polyfill-php85` passa a definir `array_first()`/`array_last()` globalmente em PHP < 8.5 — checar se o Voyager ou alguma dependência (`laravel/helpers`?) define essas funções, para evitar conflito de redeclaração.

## 4. Fases da Migração

### Fase 0 — Baseline e preparação
**Objetivo:** ter um ponto de partida limpo e reprodutível antes de tocar em código.
- [ ] Criar branch de trabalho `laravel-13` a partir do `1.8`.
- [ ] Instalar Laravel 13 + Testbench `^11.0` num ambiente de teste local (`orchestra/testbench` v11.1 é a versão compatível, exige `laravel/framework ^13.1.1` e `php ^8.3`).
- [ ] Rodar a suíte de testes atual como baseline (esperado: falhas relacionadas a DBAL).

### Fase 1 — Atualização do toolchain (PHP/Composer)
**Objetivo:** ajustar as constraints antes de qualquer mudança de código.
- [ ] `composer.json`: `"php": "^8.3"` (dropar 8.2, já que L13 exige 8.3+).
- [ ] `composer.json`: `"illuminate/support": "^13.0"` (dropar suporte a 8/9/10/11, a menos que se opte por manter compatibilidade multi-versão — ver observação abaixo).
- [ ] `require-dev`: `"laravel/framework": "^13.0"`, `"orchestra/testbench": "^11.0"`, `"phpunit/phpunit": "^11.5|^12.0"`.
- [ ] `require`: `"league/flysystem": "^3.25"` (Laravel 13 já usa Flysystem 3.25+ internamente; apertar a constraint evita conflitos de resolução).

> **Decisão a tomar:** suportar *apenas* Laravel 13, ou manter uma faixa (ex.: `~12.0|~13.0`)? Isso muda o esforço da Fase 2 — se precisar manter compatibilidade com versões anteriores que ainda tinham DBAL parcialmente disponível via pacote separado, a reescrita do schema layer precisa de um shim condicional. Recomendo **suporte apenas a 13.x** para este release, dado que o objetivo é modernizar o fork.

### Fase 2 — Remoção definitiva do Doctrine DBAL (CRÍTICO / bloqueador)
**Objetivo:** eliminar a dependência de uma API que não existe mais no Laravel, reescrevendo o Database Manager do Voyager.
- [ ] Auditar todos os ~59 arquivos em `src/Database/Types/**` e `src/Database/Schema/**` que importam classes `Doctrine\DBAL\*`.
- [ ] Substituir `$connection->getDoctrineSchemaManager()->listTableNames()` (`SchemaManager.php:156`) pela API nativa `Illuminate\Support\Facades\Schema::getTables()` / `Schema::getColumns()` / `Schema::getIndexes()` (disponíveis desde o Laravel 11 como substituto oficial do DBAL).
- [ ] Reescrever `src/Database/Types/Type.php` e as subclasses por engine (MySQL/PostgreSQL/SQLite) para trabalhar com os arrays associativos retornados pela API nativa de introspecção, em vez de objetos `DoctrineType`.
- [ ] Reativar o **Database Manager** (atualmente desabilitado desde a migração para Laravel 11, conforme aviso no README) — este é o principal ganho funcional desta fase.
- [ ] Cobrir com testes de integração por engine de banco (MySQL, PostgreSQL, SQLite) já que a introspecção de schema varia bastante entre eles.
- [ ] Remover `doctrine/dbal` do `composer.json` (se ainda listado) e de `require-dev`.

*Esta é a fase de maior risco e esforço do plano — é essencialmente uma reescrita, não um ajuste de compatibilidade.*

### Fase 3 — Modernização da suíte de testes
**Objetivo:** parar de depender de um pacote de teste abandonado.
- [ ] Remover `laravel/browser-kit-testing` e `orchestra/testbench-browser-kit` do `composer.json`.
- [ ] Migrar `tests/TestCase.php` de `Orchestra\Testbench\BrowserKit\TestCase` para `Orchestra\Testbench\TestCase` padrão (PHPUnit puro) ou avaliar migração para Pest.
- [ ] Reescrever os testes que dependem de assertions estilo BrowserKit (`$this->visit()`, `$this->see()`, etc.) para os equivalentes em `Illuminate\Foundation\Testing` (`$this->get()`, `assertSee()`, ...).
- [ ] Atualizar `phpunit.xml` se necessário para o schema do PHPUnit 11/12.

### Fase 4 — Compatibilidade de dependências de terceiros
**Objetivo:** garantir que tudo que o Voyager consome também funciona em PHP 8.3+/Laravel 13.
- [ ] `intervention/image`: avaliar migração de `^2.7` para `^3.0` — API mudou (`Image::make()` → `ImageManager::read()`), impacta `src/Http/Controllers/ContentTypes/Image.php`, `MultipleImage.php`, `VoyagerMediaController.php`. Alternativa: manter fixo em `^2.7` se ainda funcionar em PHP 8.3 (verificar).
- [ ] `arrilot/laravel-widgets`: confirmar se a versão mais recente (3.15.x, ativa) já aceita `illuminate/support ^13.0` (constraint atual é `>=11`, o que tende a aceitar, mas precisa validar em teste real).
- [ ] `laravel/ui`: reavaliar se ainda é necessário — o pacote continua mantido, mas o próprio Laravel 13 não o inclui mais por padrão em novos projetos. Se o Voyager só usa scaffolding básico de auth, considerar remover a dependência.
- [ ] Rodar `composer why-not laravel/framework 13.0` (ou equivalente) para pegar qualquer conflito não previsto.

### Fase 5 — Ajustes de código para mudanças de comportamento do Laravel 13
**Objetivo:** cobrir os pontos "baixo impacto, mas relevantes" listados na seção 3.
- [ ] Testar manualmente a paginação no painel admin (Bootstrap views).
- [ ] Auditar observers/boot hooks de models do Voyager quanto à restrição de instanciação durante boot.
- [ ] Checar se alguma dependência declara `array_first()`/`array_last()` globalmente (conflito com o polyfill do PHP 8.5 do Symfony).
- [ ] Validar `Container::call` em qualquer lugar do Voyager que injete parâmetros nullable sem binding explícito.
- [ ] (Opcional, cosmético) Modernizar a sintaxe de rotas em `routes/voyager.php` de array (`['uses' => ...]`) para a forma atual (`Route::get(...)->name(...)`).

### Fase 6 — CI/CD
**Objetivo:** validar automaticamente contra Laravel 13 e parar de testar contra versões já não suportadas.
- [ ] `.github/workflows/test.yml`: matriz `php: [8.3, 8.4]`, `laravel: ['13.*']`.
- [ ] `.github/workflows/coverage.yml`: atualizar `php-version` e o require de `illuminate/support` para `13.*`.
- [ ] Decidir se mantém matriz múltipla (ex.: testar 11/12/13 em paralelo) ou foca só em 13 — recomendo focar só em 13 dado o objetivo de modernização do fork.

### Fase 7 — Frontend/assets (opcional / stretch goal)
**Objetivo:** não é bloqueador para "rodar em Laravel 13", mas é dívida técnica que vale registrar separadamente.
- [ ] Migrar de Laravel Mix (`webpack.mix.js`) para Vite, que é o padrão do Laravel desde a v9+ e o único suportado nos starter kits do L13.
- [ ] Avaliar upgrade de Vue 2.7 (EOL) para Vue 3.
- [ ] Avaliar upgrade de Bootstrap 3.4 para uma versão suportada (4 ou 5).

> Recomendação: tratar a Fase 7 como um **release separado** (ex.: v2.1 ou v3.0), para não misturar a migração de backend (que já é grande) com uma reescrita de frontend.

### Fase 8 — Documentação e release
- [ ] Atualizar `README.md`: remover o aviso sobre o Database Manager estar quebrado (resolvido na Fase 2), atualizar "supporting Laravel 13 and newer!".
- [ ] Atualizar `composer.json` (`description`, constraints finais).
- [ ] Escrever `CHANGELOG.md` (se não existir, criar) documentando breaking changes desta versão.
- [ ] Criar tag semver e publicar/atualizar no Packagist.

## 5. Riscos principais

1. **Fase 2 (DBAL) é o verdadeiro gargalo.** Todo o resto do plano é relativamente mecânico; a reescrita do Database Manager é a única parte com risco real de bugs sutis (introspecção de schema difere por engine de banco).
2. **`intervention/image` v3** tem breaking changes de API — se optar por migrar, é um esforço à parte que toca vários controllers.
3. Pacotes de terceiros pouco usados (`arrilot/laravel-widgets`) podem não ter sido testados contra Laravel 13 por seus mantenedores — só se confirma rodando de verdade.

## 6. Critério de "pronto"

- [ ] `composer install` limpo com Laravel 13 + PHP 8.3/8.4, sem conflitos de dependência.
- [ ] Suíte de testes passando 100% sem `browser-kit-testing`.
- [ ] Database Manager funcional (CRUD de tabelas/colunas) contra MySQL, PostgreSQL e SQLite.
- [ ] CI verde na matriz `php: [8.3, 8.4]` × `laravel: [13.*]`.
- [ ] README e composer.json refletindo o novo suporte.

## 7. Versão sugerida

**Recomendação: `v2.0.0`.**

Motivos:
- É a primeira versão publicada sob a nova identidade do fork (`ilsondev/voyager`), o que já por si só justifica reiniciar a numeração de forma visível.
- Contém **breaking changes reais**: PHP mínimo sobe para 8.3, suporte a Laravel 11 é descontinuado, `browser-kit-testing` é removido, e a API interna do Database Manager é reescrita — pelo SemVer, isso exige um bump de major, não minor/patch.
- Sinaliza claramente aos consumidores que esta não é "mais uma correção" do Voyager antigo, e sim uma nova fase mantida ativamente.

Alternativa (se preferir manter a numeração herdada do projeto original, que mapeava minors para majors do Laravel — 1.6→L8/9, 1.7→L10, 1.8→L11): seria `1.9.0` ou `1.10.0`. Não recomendo essa opção porque essa convenção some justamente porque o breaking change é grande demais para parecer um "próximo minor" na cabeça de quem for atualizar via Composer.
