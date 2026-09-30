# План: правило «пробег ≤ 400 000 км, иначе review»

ID: MILEAGE. Соответствует `docs/setup/code_map.md` (1.2.2).
Сверка: `docs/intent/intent_MILEAGE.md` → `docs/spec/spec_MILEAGE.md` → этот план.
Кода в плане нет, кроме сигнатур.

## 0. Сверка со спеком

Источник истины — `docs/spec/spec_MILEAGE.md`. Ниже — трассировка
`REQ` → раздел плана и `AC` → раздел плана.

### REQ → раздел плана

- **REQ-MILEAGE-01 (порог = 400 000)** → §1, §2 шаг 1 (запись порога в
  `rules.php → vehicle.max_mileage_for_approve_km`).
- **REQ-MILEAGE-02 (≤ порога → правило не срабатывает)** → §1, §2 шаги
  2 и 5 (ветка пробега в `DecisionEngine::decide` стоит после `reject`
  и до `approve`, при `mileage <= 400000` не меняет исход LTV);
  §3 — `mileage=399999/400000/0` при низком LTV и LTV в серой зоне
  (AC-MILEAGE-01, -02, -05, -06, -07).
- **REQ-MILEAGE-03 (> порога → review)** → §1, §2 шаги 2 и 5
  (ветка пробега превращает исход `approve` в `review`); §3 —
  `mileage=400001` при низком LTV (AC-MILEAGE-03).
- **REQ-MILEAGE-04 (reject по LTV важнее review по пробегу)** → §1,
  §2 шаг 2 (ветка `reject` по LTV стоит **перед** веткой пробега);
  §3 — `mileage=400001` при LTV=120.0 (AC-MILEAGE-04).

### AC → раздел плана

| AC | Где покрыт |
|---|---|
| AC-MILEAGE-01 (399 999 / LTV=30 → approve) | §3 `DecisionEngineTest.testMileageThresholdWithLowLtv(mileage=399999)`; `AssessmentServiceTest.testApprovesWhenMileageBelowThreshold` |
| AC-MILEAGE-02 (400 000 / LTV=30 → approve) | §3 `DecisionEngineTest.testMileageThresholdWithLowLtv(mileage=400000)`; `AssessmentServiceTest.testApprovesWhenMileageAtThreshold` |
| AC-MILEAGE-03 (400 001 / LTV=30 → review) | §3 `DecisionEngineTest.testMileageThresholdWithLowLtv(mileage=400001)`; `AssessmentServiceTest.testReviewsWhenMileageJustAboveThreshold` |
| AC-MILEAGE-04 (400 001 / LTV=120 → reject) | §3 `DecisionEngineTest.testRejectBeatsReviewWhenBothTriggersFire`; `AssessmentServiceTest.testRejectsWhenHighLtvEvenWithHighMileage` |
| AC-MILEAGE-05 (mileage=0 / LTV низкий → approve) | §3 `AssessmentServiceTest.testApprovesWithZeroMileage` |
| AC-MILEAGE-06 (mileage=400 000 / LTV=72.3 → review) | §3 `DecisionEngineTest.testLtvDrivesReviewWhenMileageAtThreshold`; `AssessmentServiceTest.testLtvDrivesReviewWhenMileageAtThreshold` |
| AC-MILEAGE-07 (mileage=399 999 / LTV=120 → reject) | §3 `DecisionEngineTest.testLtvDrivesRejectWhenMileageBelowThreshold`; `AssessmentServiceTest.testLtvDrivesRejectWhenMileageBelowThreshold` |
| AC-MILEAGE-08 (нет поля mileage → 422) | §3 `AssessmentServiceTest.testRejectsMissingMileageAsInvalid` (только фиксация текущего поведения, **не меняем**) |
| AC-MILEAGE-09 (mileage='' → approve) | §3 `AssessmentServiceTest.testAcceptsEmptyStringMileageAsZero` (только фиксация текущего поведения, **не меняем**) |

### Закрытые допущения (зафиксированы спекой)

Ранее в плане были «Вопросы без заказчика». Часть из них спека уже
закрыла — отмечаю здесь со ссылкой на REQ, чтобы реализация не
переоткрывала:

- **Вопрос 1 (граница включительно/исключительно)** — **закрыт спекой
  REQ-MILEAGE-02** (см. также AC-MILEAGE-02): правило не срабатывает
  при `mileage ≤ 400 000`, то есть граница включительно
  (`mileage <= 400000 → approve`, `mileage > 400000 → review`).
  Помечавшиеся как допущение кейсы `mileage=400000 → approve`
  остаются в плане уже без отметки «допущение».
- **Вопрос 2 (что важнее — reject или review при одновременном
  срабатывании)** — **закрыт спекой REQ-MILEAGE-04** (см. также
  AC-MILEAGE-04): побеждает `reject`, ветка пробега не понижает
  `reject` до `review`. Кейсы
  `mileage=400001 / LTV=120 → reject` остаются без отметки
  «допущение».
- **Вопрос 4 (имя ключа в `rules.php`)** — **закрыт
  `docs/intent/grill_MILEAGE.md` Q5**: ключ
  `max_mileage_for_approve_km` в секции `vehicle`. Применяем без
  альтернатив.
- **Вопрос 5 (секция в `rules.php`)** — **закрыт
  `docs/intent/grill_MILEAGE.md` Q6**: секция `vehicle`. Применяем
  без альтернатив.

### Открытый вопрос (OQ-MILEAGE-01) — в этом плане **не решается**

- **Вопрос 3 / OQ-MILEAGE-01 (пустой/неизвестный пробег)** —
  спекой **перенесён к риск-менеджеру, отдельной задачей** (см.
  `docs/spec/spec_MILEAGE.md`, раздел «Статус Open questions из
  intent»). Поведение валидатора для отсутствующего поля и пустой
  строки в этом изменении **не меняется**:
  - `mileage` отсутствует или `null` → `ValidationException` с
    ключом `mileage` (HTTP 422). Фиксируем тестом
    `testRejectsMissingMileageAsInvalid` (AC-MILEAGE-08).
  - `mileage = ''` → проходит валидацию, идёт в движок как `0`,
    ветка пробега не срабатывает, `decision = approve`. Фиксируем
    тестом `testAcceptsEmptyStringMileageAsZero` (AC-MILEAGE-09).
  Реализация не трогает `ApplicationValidator` и не вводит
  отдельную ветку «нет пробега → review». Это явный запрет
  для этого плана (см. §4 «Что не входит»).

## 1. Файлы — что и где меняем

По строке на файл. Всё, чего нет в списке, при реализации не трогать.

- `backend/config/rules.php` — в секцию `vehicle` добавить порог
  `max_mileage_for_approve_km` (значение `400000`). Существующий
  `max_mileage_km = 500000` остаётся как верхняя граница валидации;
  не переименовываем и не удаляем. Имя ключа и секция зафиксированы
  `grill_MILEAGE.md` Q5/Q6.
- `backend/src/Domain/DecisionEngine.php` — расширить конструктор: принимать
  массив `['approve_max' => float, 'review_max' => float, 'max_mileage_for_approve_km' => int]`;
  сохранить новое поле; изменить сигнатуру
  `decide(float $ltv, int $mileage): string` и описать в ней порядок веток:
  сначала `reject` по LTV (`LTV > review_max`), затем `review` по пробегу
  (`mileage > max_mileage_for_approve_km`), затем `approve` по LTV
  (`LTV < approve_max`), иначе `review` по LTV. То есть ветка пробега
  стоит **после** `reject` и **до** `approve`, чтобы не превращать `reject`
  в `review` (REQ-MILEAGE-04) и перекрывать `approve` (REQ-MILEAGE-03).
- `backend/src/AppFactory.php:37` — сейчас движок создаётся как
  `new DecisionEngine($rules['ltv'])` и секцию `vehicle` не видит. Изменить
  эту строку так, чтобы в движок попал и порог пробега. Способ: собирать
  единый массив порогов из `ltv` + `vehicle.max_mileage_for_approve_km`
  (например, `['approve_max' => ..., 'review_max' => ..., 'max_mileage_for_approve_km' => $rules['vehicle']['max_mileage_for_approve_km']]`)
  и передавать его в `new DecisionEngine(...)`.
- `backend/src/Domain/AssessmentService.php` — в `assess()` передать
  `$input['mileage']` вторым аргументом в
  `$this->decisionEngine->decide($ltv, $input['mileage'])`.
- `tests/Unit/DecisionEngineTest.php` — в `setUp()` пробросить новый порог
  `400000` в конструктор движка; добавить data provider и тесты на пробег
  (см. §3); существующие LTV-кейсы сохранить, пороги LTV не менять.
- `tests/Unit/AssessmentServiceTest.php:27` — в `setUp()` тоже создаётся
  `new DecisionEngine($rules['ltv'])`; эту строку обновить так же, как
  `AppFactory.php:37`, чтобы тест собирал движок с порогом пробега.

## 2. Шаги реализации по порядку

Порядок: сначала красные тесты, потом код. Два этапа: **A** (только
`tests/`) и **B** (только `backend/`). Внутри этапа шаги привязаны
к REQ/AC.

### Этап A — только `tests/` (красные тесты)

Все правки только в `tests/Unit/`. `backend/` не трогать. После этапа
`make test` ожидаемо **красный**: новые и обновлённые тесты
падают, потому что движок ещё не принимает порог пробега и/или не
получает `mileage`.

A1. **`tests/Unit/DecisionEngineTest.php`.** В `setUp()` собрать
    движок с порогом пробега `400000` (как в §1 для `AppFactory.php`,
    REQ-MILEAGE-01). Существующему data provider `ltvValues` оставить,
    в нём пробег передавать `0` (REQ-MILEAGE-02 — не пересекает
    порог, кейсы LTV не ломаются).
A2. **`tests/Unit/DecisionEngineTest.php`.** Добавить data provider
    `mileageValues` и параметризованный тест
    `testMileageThresholdWithLowLtv(int $mileage)` — AC-MILEAGE-01,
    -02, -03 (REQ-MILEAGE-02, -03).
A3. **`tests/Unit/DecisionEngineTest.php`.** Добавить
    `testRejectBeatsReviewWhenBothTriggersFire` (AC-MILEAGE-04,
    REQ-MILEAGE-04) и тесты на серую зону LTV при пробеге на/ниже
    порога: `testLtvDrivesReviewWhenMileageAtThreshold`
    (AC-MILEAGE-06) и `testLtvDrivesRejectWhenMileageBelowThreshold`
    (AC-MILEAGE-07). Все три опираются на порядок веток в
    `DecisionEngine::decide()`.
A4. **`tests/Unit/AssessmentServiceTest.php:27`.** В `setUp()` собрать
    движок с порогом пробега так же, как в §1 для `AppFactory.php`,
    чтобы тестовая среда давала движок с порогом пробега
    (REQ-MILEAGE-01).
A5. **`tests/Unit/AssessmentServiceTest.php`.** Добавить тесты на
    пробег: `testApprovesWhenMileageBelowThreshold` (AC-MILEAGE-01),
    `testApprovesWhenMileageAtThreshold` (AC-MILEAGE-02),
    `testReviewsWhenMileageJustAboveThreshold` (AC-MILEAGE-03),
    `testRejectsWhenHighLtvEvenWithHighMileage` (AC-MILEAGE-04),
    `testApprovesWithZeroMileage` (AC-MILEAGE-05),
    `testLtvDrivesReviewWhenMileageAtThreshold` (AC-MILEAGE-06),
    `testLtvDrivesRejectWhenMileageBelowThreshold` (AC-MILEAGE-07).
A6. **`tests/Unit/AssessmentServiceTest.php`.** Добавить тесты на
    текущее поведение валидатора — **фиксация, не изменение**:
    `testRejectsMissingMileageAsInvalid` (AC-MILEAGE-08) и
    `testAcceptsEmptyStringMileageAsZero` (AC-MILEAGE-09). См. §0
    «OQ-MILEAGE-01» — в этом плане валидатор не трогаем.
A7. `make test` — **ожидаемо красный**. Падают как минимум:
    `DecisionEngineTest.testMileageThresholdWithLowLtv`,
    `testRejectBeatsReviewWhenBothTriggersFire`,
    `testLtvDrivesReviewWhenMileageAtThreshold`,
    `testLtvDrivesRejectWhenMileageBelowThreshold` (сигнатура
    `decide()` ещё старая и/или порог не проброшен), а также новые
    тесты `AssessmentServiceTest` (движок в `setUp()` ещё без
    порога пробега).

### Этап B — только `backend/` (зелёные тесты)

Все правки только в `backend/`. `tests/` на этом этапе **не
трогать**. После этапа `make test` ожидаемо **зелёный**.

B1. **REQ-MILEAGE-01.** Добавить `max_mileage_for_approve_km => 400000`
    в `rules.php → vehicle`. Имя ключа и секция — по
    `grill_MILEAGE.md` Q5/Q6.
B2. **REQ-MILEAGE-02, -03, -04.** Расширить `DecisionEngine::__construct`
    и `decide()` согласно §1; не трогать существующие ветки LTV,
    только встроить ветку пробега в правильное место (после
    `reject`, до `approve`). Порядок веток закрывает REQ-MILEAGE-04
    (`reject` важнее) и REQ-MILEAGE-03 (ветка пробега перекрывает
    `approve`).
B3. **REQ-MILEAGE-01..-04.** В `AppFactory.php:37` собрать единый
    массив порогов из `ltv` и `vehicle.max_mileage_for_approve_km`,
    передать его в `new DecisionEngine(...)`.
B4. **REQ-MILEAGE-02, -03.** В `AssessmentService::assess()` прокинуть
    `$input['mileage']` вторым аргументом в
    `$this->decisionEngine->decide($ltv, $input['mileage'])`.
B5. `make lint` и `make test` — **ожидаемо зелёный**: этап A
    зафиксировал ожидаемое поведение, этап B его реализовал. Старые
    тесты продолжают проходить (пробег `0` в существующем
    `ltvValues`, `mileage=96000` в существующих кейсах
    `AssessmentServiceTest` — оба ниже нового порога).

## 3. Тесты

Граничные значения пробега — отдельными кейсами. Каждый кейс
привязан к AC. AC-MILEAGE-08 и AC-MILEAGE-09 фиксируют **текущее**
поведение валидатора (не меняем его в этом плане).

### `tests/Unit/DecisionEngineTest.php`

Новый data provider `mileageValues` и параметризованный тест
`testMileageThresholdWithLowLtv(int $mileage)` — параметризация по
пробегу, в каждом кейсе LTV низкий (30.0), чтобы подтвердить, что
ветка пробега перекрывает approve (REQ-MILEAGE-03, REQ-MILEAGE-02).
Имя покрывает все три кейса: `399999 → approve`, `400000 → approve`
(граница включительно), `400001 → review`:

- `mileage=399999, ltv=30.0` → `approve` (AC-MILEAGE-01)
- `mileage=400000, ltv=30.0` → `approve` (AC-MILEAGE-02, граница
  включительно, см. §0 «закрытые допущения», вопрос 1)
- `mileage=400001, ltv=30.0` → `review` (AC-MILEAGE-03)

Граничные значения по пробегу: **399 999 / 400 000 / 400 001** —
покрывают «ниже порога», «ровно на пороге», «сразу за порогом» (см.
`docs/spec/README.md`: «на каждое числовое правило — AC на границе»).

Дополнительно — порядок веток (REQ-MILEAGE-04):

- `mileage=400001, ltv=120.0` → `reject` (AC-MILEAGE-04).
  Имя теста: `testRejectBeatsReviewWhenBothTriggersFire`.

Серая зона LTV при пробеге на/ниже порога (REQ-MILEAGE-02 — правило
пробега не срабатывает, решает LTV):

- `mileage=400000, ltv=72.3` → `review` (AC-MILEAGE-06).
  Имя теста: `testLtvDrivesReviewWhenMileageAtThreshold`.
- `mileage=399999, ltv=120.0` → `reject` (AC-MILEAGE-07).
  Имя теста: `testLtvDrivesRejectWhenMileageBelowThreshold`.

Существующий data provider `ltvValues` оставить как есть; в нём пробег
передавать `0` (не пересекает порог 400000, REQ-MILEAGE-02), чтобы
кейсы LTV не ломались. AC-MILEAGE-05 (`mileage=0 / LTV низкий →
approve`) проверяется на уровне `AssessmentServiceTest` (см. ниже).

### `tests/Unit/AssessmentServiceTest.php`

Новые тесты, использующие обновлённый `setUp()` (читает реальный
`rules.php`):

- `testApprovesWhenMileageBelowThreshold` — `payload(... mileage=399999)`,
  LTV низкий → `approve` (AC-MILEAGE-01, REQ-MILEAGE-02).
- `testApprovesWhenMileageAtThreshold` — `payload(... mileage=400000)`,
  LTV низкий → `approve` (AC-MILEAGE-02, REQ-MILEAGE-02, граница
  включительно, см. §0).
- `testReviewsWhenMileageJustAboveThreshold` — `payload(... mileage=400001)`,
  LTV низкий → `review` (AC-MILEAGE-03, REQ-MILEAGE-03).
- `testRejectsWhenHighLtvEvenWithHighMileage` — `mileage=400001`,
  LTV высокий (>85) → `reject` (AC-MILEAGE-04, REQ-MILEAGE-04).
- `testApprovesWithZeroMileage` — `payload(mileage=0)`, LTV низкий →
  `approve` (AC-MILEAGE-05, REQ-MILEAGE-02).
- `testLtvDrivesReviewWhenMileageAtThreshold` — `mileage=400000`,
  LTV=72.3 (серая зона) → `review` (AC-MILEAGE-06, REQ-MILEAGE-02).
- `testLtvDrivesRejectWhenMileageBelowThreshold` — `mileage=399999`,
  LTV=120.0 → `reject` (AC-MILEAGE-07, REQ-MILEAGE-02).
- `testRejectsMissingMileageAsInvalid` — `payload` без поля `mileage`
  (или `mileage=null`) → ожидаем `ValidationException` от
  `ApplicationValidator` (отсутствующее поле и `null` дают
  `mileage = -1` и ошибку валидации, HTTP 422). Зафиксировать в
  тесте ключ ошибки `mileage`. **AC-MILEAGE-08 — фиксация
  текущего поведения, в этом плане не меняется** (см. §0
  «OQ-MILEAGE-01»).
- `testAcceptsEmptyStringMileageAsZero` — `payload(mileage: '')`,
  LTV низкий → `assess()` возвращает `decision=approve`, потому что
  `ApplicationValidator.php:43` приводит `''` к `(int)0`, и пробег 0
  не пересекает порог (REQ-MILEAGE-02). **AC-MILEAGE-09 — фиксация
  текущего поведения, в этом плане не меняется** (см. §0
  «OQ-MILEAGE-01»).

Граничные значения по пробегу — **399 999 / 400 000 / 400 001**
(AC-MILEAGE-01..-04, -06); граничные значения по LTV — **ниже
`approve_max` (30.0), в серой зоне (72.3), выше `review_max` (120.0)**
(AC-MILEAGE-01..-04, -06, -07); AC-MILEAGE-05 покрывает граничный
пробег `0`.

## 4. Риски

### Что может сломаться

- **Существующая проверка `max_mileage_km = 500000` в `ApplicationValidator`** —
  остаётся как верхняя граница валидации (HTTP 422 при `mileage > 500000`).
  Новый порог `400000` — отдельное число, для решения, не для валидации.
  Если перепутать и заменить 500000 на 400000, сузится коридор допустимых
  заявок и сломается текущее поведение «500 000 — валидно, >500 000 — 422».
- **Порядок веток в `DecisionEngine::decide()`** — ветка по пробегу должна
  стоять **после** `reject` и **до** `approve` (REQ-MILEAGE-04, REQ-MILEAGE-03).
  Если поставить её до `reject` — высокий LTV начнёт превращаться в
  `review` из-за пробега, нарушая AC-MILEAGE-04. Если после `approve` —
  пробег 400001 при низком LTV останется `approve`, нарушая AC-MILEAGE-03.
  Тест `testRejectBeatsReviewWhenBothTriggersFire` фиксирует первое,
  `testReviewsWhenMileageJustAboveThreshold` — второе.
- **Сигнатура `decide()`** — изменение публичного метода ломает любой
  прямой вызов. В коде сейчас только `AssessmentService::assess()`;
  проверить, что других вызовов нет.
- **Сборка `DecisionEngine` в `AppFactory.php:37` и
  `AssessmentServiceTest.php:27`** — сейчас оба места создают движок
  через `new DecisionEngine($rules['ltv'])` и не видят секцию `vehicle`.
  Если забыть обновить тестовый `setUp()`, `AssessmentServiceTest`
  продолжит работать со старым движком и новые тесты на пробег упадут
  с неожиданными `approve` вместо `review`.
- **Хардкод чисел** — по конвенциям AGENTS.md порог 400000 нельзя
  держать в `DecisionEngine` или `AssessmentService`, только в `rules.php`.
- **Существующие тесты** — `AssessmentServiceTest` использует
  `mileage=96000`, не задевает новый порог; `DecisionEngineTest` — LTV-кейсы
  без пробега. После изменения сигнатуры `decide()` старые кейсы должны
  продолжать компилироваться: в существующем data provider `ltvValues`
  пробег передавать `0`.
- **AC-MILEAGE-08 / AC-MILEAGE-09 как «фиксация поведения»** — эти
  тесты намеренно фиксируют **текущее** поведение
  `ApplicationValidator` (отсутствующее поле → 422, `''` → 0 → approve).
  Реализация **не должна** менять валидатор ради этих тестов. Если
  поведение валидатора к моменту реализации уже изменилось (маловероятно,
  но возможно при другой задаче), тесты переписать под новое поведение
  и согласовать со спекой/риск-менеджером; в этом плане менять
  валидатор **запрещено**.

### Что не входит

- Изменение `max_mileage_km` (500 000) и текста ошибки валидации.
- Переписывание `ApplicationValidator` — только проброс существующего
  `$input['mileage']` дальше по цепочке. В частности, **не** ужесточать
  валидатор для `mileage = ''` (это часть OQ-MILEAGE-01, решает
  риск-менеджер отдельной задачей).
- Изменение `LtvCalculator`, репозитория, БД, frontend, seed-данных.
- Новая логика `approved_limit` и расчёт по `ltv_by_age` (задача LOAN-12).
- Автотесты на feature/уровне (HTTP) — в репозитории только Unit-тесты.
- Реакция фронта на `review` из-за пробега (отображение в UI не меняется).
- Ветка «нет пробега → review» (часть OQ-MILEAGE-01).

## Вопросы без заказчика

Все исходные вопросы плана либо закрыты спекой, либо перенесены.
Ниже — сводка.

1. **Граница включительно/исключительно** — **закрыто** спекой
   REQ-MILEAGE-02: `mileage ≤ 400000` (включительно). См. §0.
2. **Что важнее — reject или review при одновременном срабатывании** —
   **закрыто** спекой REQ-MILEAGE-04: `reject` важнее. См. §0.
3. **Пустой/неизвестный пробег** — **не решается в этом плане**:
   OQ-MILEAGE-01 перенесён к риск-менеджеру, отдельной задачей
   (см. §0 и `docs/spec/spec_MILEAGE.md` раздел «Статус Open
   questions из intent»). Поведение валидатора фиксируется
   тестами AC-MILEAGE-08 и AC-MILEAGE-09 и **не меняется**.
4. **Имя ключа в `rules.php`** — **закрыто**
   `docs/intent/grill_MILEAGE.md` Q5: `max_mileage_for_approve_km`.
5. **Куда положить порог** — **закрыто**
   `docs/intent/grill_MILEAGE.md` Q6: секция `vehicle`.
