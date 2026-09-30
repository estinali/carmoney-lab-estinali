# План: правило «пробег ≤ 400 000 км, иначе review»

ID: MILEAGE. Соответствует `docs/setup/code_map.md` (1.2.2).
Сверка перед реализацией: `docs/intent/intent_MILEAGE.md` → `docs/spec/spec_MILEAGE.md`.
Кода в плане нет, кроме сигнатур.

## 1. Файлы — что и где меняем

По строке на файл. Всё, чего нет в списке, при реализации не трогать.

- `backend/config/rules.php` — в секцию `vehicle` добавить порог
  `max_mileage_for_approve_km` (значение `400000`). Существующий
  `max_mileage_km = 500000` остаётся как верхняя граница валидации;
  не переименовываем и не удаляем.
- `backend/src/Domain/DecisionEngine.php` — расширить конструктор: принимать
  массив `['approve_max' => float, 'review_max' => float, 'max_mileage_for_approve_km' => int]`;
  сохранить новое поле; изменить сигнатуру
  `decide(float $ltv, int $mileage): string` и описать в ней порядок веток:
  сначала `reject` по LTV (`LTV > review_max`), затем `review` по пробегу
  (`mileage > max_mileage_for_approve_km`), затем `approve` по LTV
  (`LTV < approve_max`), иначе `review` по LTV. То есть ветка пробега
  стоит **после** `reject` и **до** `approve`, чтобы не превращать `reject`
  в `review` и перекрывать `approve`.
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

1. Добавить `max_mileage_for_approve_km => 400000` в `rules.php → vehicle`.
2. Расширить `DecisionEngine::__construct` и `decide()` согласно §1;
   не трогать существующие ветки LTV, только встроить ветку пробега
   в правильное место (после `reject`, до `approve`).
3. В `AppFactory.php:37` собрать единый массив порогов из `ltv` и
   `vehicle.max_mileage_for_approve_km`, передать его в `new DecisionEngine(...)`.
4. В `AssessmentServiceTest.php:27` повторить ту же сборку порогов,
   чтобы тестовый `setUp()` давал движок с порогом пробега.
5. Прокинуть `$input['mileage']` из `AssessmentService::assess()` в `decide()`.
6. Обновить `DecisionEngineTest`: `setUp()` + data provider + тесты на границы.
7. Добавить тесты в `AssessmentServiceTest` на пробег (границы и пустой/нулевой).
8. `make lint` и `make test`; убедиться, что старые тесты зелёные.

## 3. Тесты

Граничные значения пробега — отдельными кейсами. Дополнительно — пустое
поле, `null` и пустая строка. Часть кейсов помечена как «допущение» — это
открытые вопросы к заказчику (§«Вопросы без заказчика»).

### `tests/Unit/DecisionEngineTest.php`

Новый data provider `mileageValues` и тест
`testHighMileageForcesReviewEvenWhenLtvAllowsApprove(int $mileage)` —
параметризация по пробегу, в каждом кейсе LTV низкий (например, 30.0),
чтобы подтвердить, что ветка пробега перекрывает approve:

- `mileage=399999, ltv=30.0` → `approve`
- `mileage=400000, ltv=30.0` → `approve` (на границе ещё не review;
  допущение — см. вопрос 1)
- `mileage=400001, ltv=30.0` → `review`

Дополнительно — порядок веток:

- `mileage=400001, ltv=120.0` → `reject`
  (допущение: `reject` важнее `review`; см. вопрос 2).
  Имя теста: `testRejectBeatsReviewWhenBothTriggersFire`.

Существующий data provider `ltvValues` оставить как есть; в нём пробег
передавать `0` (не пересекает порог 400000), чтобы кейсы LTV не ломались.

### `tests/Unit/AssessmentServiceTest.php`

Новые тесты, использующие обновлённый `setUp()` (читает реальный `rules.php`):

- `testApprovesWhenMileageBelowThreshold` — `payload(... mileage=399999)`,
  LTV низкий → `approve`.
- `testApprovesWhenMileageAtThreshold` — `payload(... mileage=400000)`,
  LTV низкий → `approve` (допущение: граница включительно; см. вопрос 1).
- `testReviewsWhenMileageJustAboveThreshold` — `payload(... mileage=400001)`,
  LTV низкий → `review` (перекрытие approve).
- `testRejectsWhenHighLtvEvenWithHighMileage` — `mileage=400001`,
  LTV высокий (>85) → `reject` (допущение: `reject` важнее `review`;
  см. вопрос 2).
- `testRejectsMissingMileageAsInvalid` — `payload` без поля `mileage`
  → ожидаем `ValidationException` от `ApplicationValidator`
  (отсутствующее поле и `null` дают `mileage = -1` и ошибку валидации).
  Зафиксировать в тесте ключ ошибки `mileage`.
- `testAcceptsEmptyStringMileageAsZero` — `payload(mileage: '')` →
  ожидаем успешный `assess()` с `decision` по LTV, потому что
  `ApplicationValidator.php:43` приводит `''` к `(int)0`, и пробег 0
  не пересекает порог (допущение — см. вопрос 3: должно ли пустое
  строковое значение считаться ошибкой, а не нулём).

## 4. Риски

### Что может сломаться

- **Существующая проверка `max_mileage_km = 500000` в `ApplicationValidator`** —
  остаётся как верхняя граница валидации (HTTP 422 при `mileage > 500000`).
  Новый порог `400000` — отдельное число, для решения, не для валидации.
  Если перепутать и заменить 500000 на 400000, сузится коридор допустимых
  заявок и сломается текущее поведение «500 000 — валидно, >500 000 — 422».
- **Порядок веток в `DecisionEngine::decide()`** — ветка по пробегу должна
  стоять **после** `reject` и **до** `approve`. Если поставить её до
  `reject` — высокий LTV начнёт превращаться в `review` из-за пробега.
  Если после `approve` — пробег 400001 при низком LTV останется
  `approve`. Тест `testRejectBeatsReviewWhenBothTriggersFire` фиксирует
  первое, `testReviewsWhenMileageJustAboveThreshold` — второе.
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

### Что не входит

- Изменение `max_mileage_km` (500 000) и текста ошибки валидации.
- Переписывание `ApplicationValidator` — только проброс существующего
  `$input['mileage']` дальше по цепочке.
- Изменение `LtvCalculator`, репозитория, БД, frontend, seed-данных.
- Новая логика `approved_limit` и расчёт по `ltv_by_age` (задача LOAN-12).
- Автотесты на feature/уровне (HTTP) — в репозитории только Unit-тесты.
- Реакция фронта на `review` из-за пробега (отображение в UI не меняется).

## Вопросы без заказчика

1. **Граница включительно/исключительно**: «не больше 400 000» — это
   `mileage <= 400000` (на границе ещё `approve`) или `mileage < 400000`
   (на границе уже `review`)? В формулировке задачи «не больше 400 000»
   естественно `≤`, но в спеке AC могут уточнить. До ответа план
   закладывает `≤` (тесты `testApprovesWhenMileageAtThreshold`,
   `mileage=400000 → approve` в `DecisionEngineTest` помечены как
   допущение).
2. **Что значит «иначе review» при других решениях**: если LTV сразу даёт
   `reject` (LTV > 85), должно ли mileage-правило менять решение на
   `review`? Инженерное прочтение — `reject` сильнее (порядок веток
   в плане исходит из этого), но заказчик может хотеть иного
   («mileage > 400k всегда review, даже в ущерб reject»). До ответа
   план закладывает `reject > review` (тест
   `testRejectBeatsReviewWhenBothTriggersFire` и
   `testRejectsWhenHighLtvEvenWithHighMileage` помечены как допущение).
3. **Пустой/неизвестный пробег**: сейчас `ApplicationValidator.php:43`
   трактует отсутствующее поле и `null` как `mileage = -1` (ошибка
   валидации, 422), а пустую строку `''` — как `(int)0` (валидно,
   проходит как пробег 0 км). Это похоже на баг: в задаче пробег
   обязателен, и `''` тоже должна быть ошибкой. Нужно ли:
   (а) ужесточить валидатор, чтобы `''` тоже давал 422;
   (б) оставить как есть и считать 0 км допустимым пробегом;
   (в) трактовать «нет пробега» не как ошибку, а как `review`?
   Тесты `testRejectsMissingMileageAsInvalid` и
   `testAcceptsEmptyStringMileageAsZero` фиксируют текущее поведение
   и помечены как допущение.
4. **Имя ключа в `rules.php`**: `max_mileage_for_approve_km` — рабочее
   имя. Заказчик может предпочесть `mileage_review_threshold_km`,
   `high_mileage_km` и т.п.
5. **Куда положить порог**: в `vehicle` (рядом с `max_mileage_km`) или
   отдельной секцией `mileage`/`risk`? Сейчас план — в `vehicle`.
