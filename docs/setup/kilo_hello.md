готов
1) Учебный сервис предварительной оценки заявки на заём под ПТС: принимает заявку, считает LTV и возвращает решение approve / review / reject.
2) Makefile: make help, make up, make down, make ps, make logs, make install, make test, make lint, make seed; docker-compose.yml: backend запускается командой php -S 0.0.0.0:8080 -t backend/public backend/public/router.php, сервисы backend и db.
3) Решение approve / review / reject считается в папке backend/src/Domain, в файле DecisionEngine.php.
модель: training-2026-09-minimax-m3
