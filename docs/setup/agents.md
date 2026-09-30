# Агенты проекта (1.4.1–1.4.2)

## planner

- Файл: `.kilo/agents/planner.md`, режим primary.
- Можно: читать код, писать в `docs/plan/`.
- Нельзя: править что-либо вне `docs/plan/`, запускать bash.

Проверка прав:

1. Просьба «добавь комментарий в backend/public/index.php». Модель отказалась сама по инструкции («код не меняю, пишу план»), инструмент правки не вызвала и записала план в `docs/plan/` — разрешённая папка, запись прошла. До прав дело не дошло: это послушание модели, а не защита.
2. Просьба сохранить план в `docs/setup/plan_test.md`. Запись дважды остановлена правилом `{"permission":"edit","pattern":"*","action":"deny","source":"project"}`. После этого модель сообщила «План сохранён в docs/setup/plan_test.md», хотя файла нет — проверено через `git status`.
3. Bash недоступен: `Model tried to call unavailable tool 'bash'` — инструмента нет в наборе planner.

Вывод: права держат независимо от того, что говорит модель. Отчёту агента о сделанном верить нельзя — проверять по плашке и `git status`.

## scout

- Файл: `.kilo/agents/scout.md`, режим subagent, вызывается из planner через `@scout`.
- Можно: читать и искать по коду.
- Нельзя: править файлы, запускать bash.

## Что вернул scout

Запрос: `@scout найди все места, где читается пробег (mileage)`

Ответ scout:

> Места, где читается пробег mileage (поле из payload, валидация, репозиторий, движок, тесты, фронт):
>
> **Backend (PHP)**
> - `backend/src/Domain/ApplicationValidator.php:43-45` — чтение из payload, диапазон 0..max_mileage_km.
> - `backend/src/Domain/ApplicationValidator.php:78` — проброс в нормализованный `$input['mileage']`.
> - `backend/src/Repository/ApplicationRepository.php:38-45` — INSERT в `vehicles(mileage_km)`.
> - `backend/src/Repository/ApplicationRepository.php:68` — чтение `v.mileage_km` (если есть метод выборки).
> - `backend/config/rules.php:23` — порог `vehicle.max_mileage_km = 500000` (читается валидатором).
> - `tests/Unit/ApplicationValidatorTest.php:34`, `tests/Unit/AssessmentServiceTest.php:38` — фикстуры с mileage.
>
> **DB**
> - `db/schema.sql:22` — колонка `vehicles.mileage_km`.
> - `db/seed.sql:31` — INSERT c `mileage_km`.
>
> **Frontend**
> - `frontend/index.html:30-31` — `<input name="mileage">`.
> - `frontend/app.js:8` — mileage в `NUMERIC_FIELDS`.
>
> В движке решения (DecisionEngine) пробег сейчас не читается — `docs/setup/code_map.md:40` фиксирует, что mileage на approve/review/reject не влияет, только валидируется. Это и есть место для задачи MILEAGE.

Сверка с кодом: все 11 мест существуют, файлы и номера строк совпали; других упоминаний mileage в `backend/`, `tests/`, `db/` и `frontend/` нет. Одна неточность: про `ApplicationRepository.php:68` scout написал «если есть метод выборки» — метод есть, это `find()`. Файлы scout не менял, исправлений не предлагал.
