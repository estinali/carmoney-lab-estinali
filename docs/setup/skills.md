# Что даёт скилл code-review (задание 2.2.2)

Трек **Р**. Скилл — `.kilo/skills/code-review/SKILL.md` (+ шаблон `template.md`). Kilo, агент **code**, модель `training-2026-09-minimax-m3`, каждый прогон — новая сессия после `/reload`. Что вызвано — по ленте сессии Kilo (локальная база Kilo), а не по ответу агента.

Просьба во всех прогонах одна и та же, слова «скилл» нет:

> Посмотри backend/src/Repository/ApplicationRepository.php перед мержем.

Файл в ветке не менялся относительно `main`, так что настоящего диффа нет — ревьюится сам файл.

## Вывод

**Со скиллом агент сделал ревью по-нашему:** замечания строками `severity · файл:строка · что не так · почему` со шкалой blocker / major / minor, вердикт одним из трёх вариантов («нельзя мержить»), блок «Проверено / не проверено» (честно: `make test` / `make lint` — не проверено), сначала сверил дифф ветки с `main`, код не трогал и патчей не предлагал. **Без скилла этого не было:** ответ на английском, свои разделы Blocker / Should fix / Minor, нет вердикта, нет списка проверенного и непроверенного, вместо замечания — готовый кусок кода для правки.

Где скилл сработал не до конца: файл вне диффа PR, и по правилу №1 скилла замечания должны идти строкой «вне PR» и не менять вердикт — агент это заметил («PR не меняет этот файл»), но вердикт всё равно поставил по старым проблемам из `main`. Ещё он приписал правило «SQL только через подготовленные запросы» к AGENTS.md — на деле оно из чек-листа скилла.

## Два ответа рядом

| | Со скиллом (01:10, вызван `skill code-review`) | Без скилла (01:14, папка скилла убрана) |
|---|---|---|
| Что делал по ленте | `skill code-review` → `git status`, `git diff main...HEAD`, `git log` по файлу → `read` файла → `gh pr list` → `grep` вызовов и тестов | `read` файла, `board_read` → `read db/schema.sql`, `grep` вызовов → `read ApplicationController.php`, `git log` → `ls tests`, `grep` PDO-настроек |
| Язык | русский | английский |
| Формат замечания | `severity · файл:строка · что не так · почему` | абзацы под заголовками |
| Шкала | blocker / major / minor | Blocker / Should fix / Minor |
| Вердикт | **нельзя мержить** — есть blocker'ы | нет |
| Проверено / не проверено | есть; `make test`, `make lint`, схема БД — «не проверено» | нет |
| Правки кода | нет, только замечания | готовый PHP-патч для `listApplications()` |
| SQL-инъекция `:92` | blocker | Blocker (+ путь из HTTP через `ApplicationController.php:84-87`) |
| Нет транзакции в `save()` | major | Should fix |
| N+1 в `listApplications()` | major | Should fix |
| Нет тестов на репозиторий | blocker | Minor |
| Что нашёл только он | `$limit` без проверки границ (major), статус `'decided'` строкой (minor) | `ltv` float vs `DECIMAL(6,2)` — разные значения в POST и GET (Should fix), разная форма `find()` и `listApplications()` |

### Ответ со скиллом (дословно)

> Ревью `backend/src/Repository/ApplicationRepository.php`.
>
> **Контекст:** PR (ветка `d2/2.2.1-2.2.3-estinali`) не меняет этот файл относительно `main` — в дифф попал только `.kilo/skills/code-review/SKILL.md`. Замечания ниже относятся к самому файлу (есть и в `main`).
>
> ## Замечания
>
> - **blocker** · `backend/src/Repository/ApplicationRepository.php:92` · `$status` подставляется в SQL конкатенацией строк (`"... WHERE status = '" . $status . "'"`) · SQL-инъекция; нарушает правило AGENTS.md «SQL только через подготовленные запросы». В `save()` и `find()` плейсхолдеры используются — здесь сломан контракт.
> - **blocker** · `backend/src/Repository/ApplicationRepository.php:95` · `$limit` тоже конкатенируется в строку SQL · нарушает то же правило; типизация `int` спасает от инъекции, но требование AGENTS.md это всё равно нарушает.
> - **blocker** · `tests/` · для `ApplicationRepository` нет ни одного теста (`grep` по `listApplications`/`ApplicationRepository` пуст) · нет покрытия поведения `save/find/listApplications`; чек-лист код-ревью требует тесты под новое поведение — здесь нет даже под существующее.
> - **major** · `backend/src/Repository/ApplicationRepository.php:99-110` · N+1: на каждую строку делаются ещё два `prepare`+`execute` (vehicles, decisions) · для списка оператора на 50 заявок это 101 запрос вместо одного `JOIN`; `find()` уже делает `JOIN`, в `listApplications` логика вывернута.
> - **major** · `backend/src/Repository/ApplicationRepository.php:87` · `int $limit = 50` без проверки границ · отрицательное или нулевое значение пройдёт в SQL `LIMIT 0` / отрицательное — кейс на границе не покрыт.
> - **major** · `backend/src/Repository/ApplicationRepository.php:22-61` · `save()` вставляет три строки без `BEGIN/COMMIT` · при сбое на 2-й или 3-й вставке останется «голый» `applications` без `vehicles`/`decisions`; целостность `LEFT JOIN` в `find()` это маскирует, но данные будут битые.
> - **minor** · `backend/src/Repository/ApplicationRepository.php:32` · статус `'decided'` захардкожен строкой · лучше вынести в константу класса или `rules.php`, чтобы не расходилось с доменом.
> - **minor** · `backend/src/Repository/ApplicationRepository.php:64` vs `:87` · `find()` использует `JOIN`, `listApplications()` — N+1 · несогласованность стиля в одном классе.
>
> ## Вердикт
>
> **нельзя мержить** — есть blocker'ы (SQL-инъекция + отсутствие тестов на класс).
>
> Даже если PR формально не меняет этот файл, в `main` эти же проблемы — стоит править до того, как сервис пойдёт дальше. Перед фиксом — спросить человека, не переезжает ли `listApplications` на отдельный use case (тогда меняется и Http-слой).
>
> ## Что проверено / не проверено
>
> - Проверено: дифф ветки vs `main` (`git diff main` — пусто по `backend/`), содержимое файла, вызывающий код (`Http/ApplicationController.php:87`), наличие тестов (`grep` по репо — нет).
> - Не проверено: `make test` / `make lint` (не запускал — PR не меняет PHP-код), реальная схема `db/schema.sql` (типы колонок и допустимость пустых `LEFT JOIN`), поведение под нагрузкой N+1.

### Ответ без скилла (дословно)

> Review of `backend/src/Repository/ApplicationRepository.php` (unchanged since `089b69b`; nothing from the d2 branch touches it).
>
> **Blocker — SQL injection**
> `listApplications()` interpolates `$status` straight into the SQL and runs it via `query()` (not a prepared statement): `backend/src/Repository/ApplicationRepository.php:92`. It's reachable from HTTP — `ApplicationController::index()` passes the raw `?status=` query param through with no whitelist (`backend/src/Http/ApplicationController.php:84-87`). `DB::connect()` only sets `ERRMODE_EXCEPTION`, so a payload like `decided' OR '1'='1` leaks all rows and a malformed one throws PDOException → 500. Fix: bind it.
>
> ```php
> $sql = 'SELECT id, applicant_ref, requested_amount, term_months, status, created_at FROM applications';
> $params = [];
> if ($status !== null && $status !== '') {
>     $sql .= ' WHERE status = :status';
>     $params[':status'] = $status;
> }
> $sql .= ' ORDER BY id DESC LIMIT :limit';
>
> $stmt = $this->pdo->prepare($sql);
> $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
> $stmt->execute($params);
> $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
> ```
>
> **Should fix**
> - `save()` does three inserts with no transaction (`:24-58`). If the vehicle or decision insert fails, you keep an `applications` row with no vehicle/decision — and `find()`'s LEFT JOINs (`:71-72`) will happily return that half-written record. Wrap in `beginTransaction()` / `commit()` / `rollBack()`.
> - N+1 queries: 2 extra round-trips per row inside the loop (`:101-107`). Default page = 50 rows = 101 queries. `find()` already does it correctly with two LEFT JOINs — reuse that shape in a single query.
> - `ltv` is a PHP float (`:55`) written to `DECIMAL(6,2)` (`db/schema.sql:33`). The API response returns the unrounded float (`ApplicationController.php:47`), so `POST /api/applications` and `GET /api/applications/{id}` report different values for the same application (e.g. `0.7352941…` vs `0.74`). Round once, at the source.
>
> **Minor**
> - `:95` concatenates `$limit`; harmless for `int` under `strict_types`, but `$stmt->bindValue(..., PDO::PARAM_INT)` is the consistent form.
> - `:106-107` returns nested `vehicle`/`decision` objects while `find()` returns the same fields flat — two shapes for one entity.
> - `:63` — the "пустой массив…" note is jammed into the `@return` tag; belongs in the description.
> - Zero test coverage: `tests/Feature/` contains only a README, so none of the above is caught by `make test`.
>
> Unrelated to the file: `git status` shows `.kilo/skills/code-review/{SKILL.md,template.md}` deleted but uncommitted — worth deciding before you merge.

## ★ Агент не взял скилл с первого раза — description до и после

Скилл был виден в Kilo (Settings → Skills), но в первом прогоне (01:07) агент его не вызвал: в ленте только `read` файла, ответ в чужом формате (Critical / Performance / Style / Green flags, «не мержить до починки…» вместо вердикта из трёх вариантов). Просьбу не меняли — правили только `description`.

**До** (агент не взял):

```yaml
description: Используй, когда просят посмотреть код, PR, дифф или изменения ветки перед мержем — «глянь PR», «проверь изменения», «можно мержить?».
```

**После** (агент взял первым же вызовом — `skill {"name": "code-review"}`):

```yaml
description: Загрузи до начала работы, когда просят посмотреть, проверить или отревьюить файл, код, PR, дифф или изменения перед мержем — даже если в просьбе одно имя файла («посмотри X перед мержем», «глянь PR», «можно мержить?»).
```

Что изменилось: глагол-приказ «Загрузи до начала работы» вместо описания ситуации, «файл» в списке объектов и явная оговорка «даже если в просьбе одно имя файла» — ровно случай нашей просьбы.

# Когда скилл включается (задание 2.2.3)

Kilo, агент **code**, `training-2026-09-minimax-m3`, каждая просьба — новая сессия; слова «скилл» в просьбах нет. Исход — по ленте сессии: есть ли вызов `skill {"name": "code-review"}`.

| Просьба | Ожидание | Исход по ленте | Верно |
|---|---|---|---|
| «Глянь мои последние изменения, можно мержить?» (другими словами) | взяться | **взялся** — `skill code-review` первым вызовом, затем `git diff` ветки, `read template.md`; ответ по шаблону: «замечаний нет», вердикт «можно мержить», блок «Проверено» (`make test` / `make lint` — «не проверено», пороги и конвенции — «не применимо», PHP в диффе нет) | да |
| «Объясни, как работает ApplicationRepository.php» (похоже, но не то) | промолчать | **не взялся** — `glob`, `read` файла, `grep` вызовов, `read db/schema.sql`; ответ — объяснение по методам, без severity и вердикта | да |
| ★ «Почини SQL-инъекцию в listApplications в ApplicationRepository.php» (граница: тот же файл и та же находка, что в ревью, но работа — исправить) | промолчать | **не взялся** — `read`, `grep`, `edit` файла, проверка через `php -l`, `make lint` и запросы к поднятому сервису; итог — исправленный `listApplications()` | да |

Фикс из ★ откачен (`git checkout`), чтобы не попасть в PR про скиллы: эта просьба проверяла только, что скилл не мешает, когда просят чинить. Если бы скилл взялся, его правило «код при ревью не правлю» заблокировало бы работу.

Заметка о первой просьбе: первая сессия (01:21) стартовала до `/reload` после возврата папки скилла — агент позвал `skill code-review` и получил `Skill "code-review" not found`; сессия прервана, повтор после `/reload` (01:22) — в таблице. Агент пытался позвать скилл, которого не было в списке: имя `code-review` он, вероятно, увидел в диффе ветки, который прочитал перед этим.

**Итоговый description** — без изменений после 2.2.2, на всех трёх просьбах сработал верно:

```yaml
description: Загрузи до начала работы, когда просят посмотреть, проверить или отревьюить файл, код, PR, дифф или изменения перед мержем — даже если в просьбе одно имя файла («посмотри X перед мержем», «глянь PR», «можно мержить?»).
```
