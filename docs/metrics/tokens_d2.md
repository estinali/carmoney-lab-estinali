# Замер токенов: без индекса → ast-index (задание 2.1.1)

Трек **Р · Разработчик**. Модель `training-2026-09-minimax-m3`, Kilo, агент ask, каждый замер — новая сессия.

Промпт (одинаковый во всех замерах):

> Все места, где используются mileage и max_mileage_km: файл и функция. Отвечай по коду этого репозитория, по строке на место — файл и функция. Ничего не выдумывай: чего нет — пиши «нет».

Источник цифр — корпоративный дашборд LiteLLM (`ai-platform.carm.corp` → Personal usage), он заменяет OpenRouter Activity: Kilo ходит в модели через корпоративный прокси. Цифры — сумма строк «Истории» за сессию, сверены с разницей «Итогов периода» до и после сессии. Вызовы инструментов — из ленты сессии Kilo.

| Замер | Когда (МСК) | Запросов | input | output | $ | агент звал ast-index |
|---|---|---|---|---|---|---|
| без индекса | 21.10.2026 01:39 | 5 | 79 219 | 4 043 | 0,01316213 | нет (не установлен) |
| ast-index | 21.10.2026 01:19 | 6 | 77 838 | 2 939 | 0,01180519 | нет |
| ast-index, повтор после перезапуска VS Code | 21.10.2026 02:13 | 4 | 45 100 | 3 546 | 0,00997556 | нет |

Повтор «ast-index» сделан, чтобы исключить версию «Kilo не перечитал AGENTS.md / PATH»: VS Code полностью перезапущен, новая сессия.

Замер «без индекса» снят после «ast-index»: первая попытка 20.10 18:58 оказалась не замером (в сессии была команда `/connect`, 1 запрос, ответа нет) и в таблицу не вошла. Для чистоты на время замера строка про ast-index убрана из AGENTS.md, бинарник `ast-index` убран из PATH.

## Что делал агент (лента Kilo)

**Без индекса:** `grep mileage` и `grep max_mileage_km` по репо → прочитал целиком 10 файлов (`ApplicationValidator.php`, `AssessmentService.php`, `ApplicationRepository.php`, `rules.php`, `AssessmentServiceTest.php`, `ApplicationValidatorTest.php`, `DecisionEngineTest.php`, `AppFactory.php`, `DecisionEngine.php`, `app.js`) → `grep -rn mileage` через bash.

**ast-index:** 6 вызовов встроенного `grep` (`mileage`, `max_mileage_km`, `mileage` по `backend/src`, `tests`, `frontend`, `mileage_km` по `backend`) → прочитал целиком 6 файлов (`ApplicationValidator.php`, `DecisionEngine.php`, `AppFactory.php`, `ApplicationRepository.php`, `rules.php`, `AssessmentService.php`).

**ast-index, повтор:** `grep mileage` и `grep max_mileage_km` по репо → прочитал целиком 8 файлов (`ApplicationRepository.php`, `AppFactory.php`, `AssessmentServiceTest.php`, `ApplicationValidatorTest.php`, `DecisionEngineTest.php`, `ApplicationValidator.php`, `DecisionEngine.php`, `AssessmentService.php`) → ответ.

## ★ Какие команды индекса вызвал агент

Ни одной — ни в первом прогоне, ни в повторе. Индекс был собран (`ast-index rebuild`, 25 файлов), строка в AGENTS.md на месте, `ast-index` в PATH, bash агенту ask доступен (в замере «без индекса» он им пользовался), но агент выбирал встроенные `grep` и `read`.

## Качество ответа

Оба ответа сверены с `grep -rn mileage`: все 3 места `max_mileage_km` (`rules.php:23`, `ApplicationValidator::validate()` строки 44–45) найдены верно, места `mileage` указаны с верными строками и функциями, выдуманных мест нет. Ответ «без индекса» полнее: перечислены все тестовые методы про пробег.

## Вывод: стал ли агент искать, а не читать

Нет. В обоих прогонах с индексом агент всё равно искал grep'ом и читал файлы целиком. Цена плавает от 0,0100 $ до 0,0132 $ в зависимости от того, сколько файлов он решил открыть (6–10), — это разброс поведения модели, а не эффект индекса. Одной строки в AGENTS.md недостаточно, чтобы агент ask сменил встроенные инструменты на ast-index: нужно явнее — разрешить `ast-index *` в `kilo.jsonc` без вопроса, просить о нём прямо в промпте или оформить его как отдельный инструмент/skill.
