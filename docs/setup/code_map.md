# 1.2.2 — карта кода под фичу «пробег ≤ 400 000 км, иначе review»

Агент: **ask**, модель: **training-2026-09-glm-5.3**. Контекст: `@backend/src/Domain/`, `@backend/config/rules.php`. Файлы агент не менял.

## Ответ агента

### Как считается решение approve / review / reject

Поток заявки описан в комментарии самого сервиса: валидация → LTV → решение → лимит.

Файлы и порядок вызова:

1. `AssessmentService::assess()` — оркестратор, принимает сырой payload.
2. `ApplicationValidator::validate()` — нормализует и валидирует поля заявки:
   - `VinValidator::isValid()` — длина 17, алфавит A-Z0-9, запрещённые символы I/O/Q (из `rules.php → vin`);
   - `VehicleAge::inYears()` — возраст авто = текущий год − год выпуска; проверка `min_year`, не в будущем, `max_age_years`;
   - диапазоны суммы (`amount.min/max`), срока (`term.min_months/max_months`), стоимости (> 0), пробега (`vehicle.max_mileage_km`). При ошибках — `ValidationException`, заявка дальше не идёт.
3. `LtvCalculator::calculate()` — LTV = запрошенная сумма / оценочная стоимость × 100, округление до 2 знаков.
4. `DecisionEngine::decide()` — единственное место, где рождается решение, по порогам из `rules.php → ltv`:
   - LTV < `approve_max` (60.0) → approve;
   - `approve_max` <= LTV <= `review_max` (85.0) → review;
   - LTV > `review_max` → reject.
5. `AssessmentService::assess()` собирает ответ: `vehicle_age`, `ltv`, `decision`, `approved_limit` (= запрошенная сумма при approve, иначе 0), `input`.

Важно: решение зависит только от LTV. Возраст, пробег, VIN и т.п. влияют лишь на то, пройдёт ли заявка валидацию, а не на выбор между approve/review/reject.

### Куда встанет правило «пробег > 400 000 км → review»

Единственная функция, выдающая решение, — `DecisionEngine::decide()`, поэтому правило должно встать в неё. Сейчас она принимает только `float $ltv`. Значит, нужно:

- в `rules.php` добавить порог (например, в секцию `vehicle`), значение 400 000 — не хардкодить;
- расширить сигнатуру `decide()`, чтобы она получала пробег (вторым аргументом или весь `$input`). Чтобы «иначе review» перекрывало approve при низком LTV, проверка должна идти **до** ветки approve;
- в `AssessmentService::assess()` передать `$input['mileage']` в движок.

Что уже есть: пробег приходит в payload, валидируется в `ApplicationValidator` и лежит в нормализованном `$input['mileage']`, который есть в `AssessmentService`; константа `DecisionEngine::REVIEW` существует.

Чего не хватает:
- порога 400 000 в `rules.php` — нет;
- передачи пробега в `DecisionEngine::decide()` — нет;
- кода про mileage в `DecisionEngine` — нет;
- тестов на такое правило — нет.

### Что уже сейчас проверяется про пробег

Ровно одна проверка: `ApplicationValidator::validate()` — `0 <= mileage <= max_mileage_km` (500 000 из `rules.php → vehicle.max_mileage_km`); иначе ошибка в поле и `ValidationException` (заявка отклоняется как невалидная, HTTP 422, а не получает reject/review). На решение пробег сейчас не влияет.

## Сверка с кодом

Проверено по файлам — агент не выдумал:

| Утверждение | Где в коде |
|---|---|
| порядок validate → calculate → decide | `backend/src/Domain/AssessmentService.php`, `assess()` |
| проверка пробега 0…500 000 | `backend/src/Domain/ApplicationValidator.php:43-45`, `rules.php → vehicle.max_mileage_km` |
| `decide(float $ltv)`, `$ltv < approveMax` → approve | `backend/src/Domain/DecisionEngine.php` |
| VIN: 17 символов, `[A-Z0-9]`, без I/O/Q | `backend/src/Domain/VinValidator.php` |

Замечание: в комментарии `rules.php` написано `LTV <= approve_max → approve`, а в коде `DecisionEngine` — строго `<`. Агент верно описал поведение кода; расхождение с комментарием не упомянул.
