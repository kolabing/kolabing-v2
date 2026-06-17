# Test Accounts — Gamification Testing

> Base URL: http://127.0.0.1:8000/api/v1
> Admin panel: http://127.0.0.1:8000/admin
> Password for all accounts: password123

## API Accounts

| Account | Role | Email | Token |
|---------|------|-------|-------|
| C1 | Community leader | c1@kolabing.test | `1\|LHx5cogKhQurqOQ2l9Ag0bTVSaR9N0Q3a0Y6ia7m277db10c` |
| C2 | Community member | c2@kolabing.test | `3\|CmCjCQ4fPkUAS8nLxnNjFSLlAurKhCayvTE319Xl35b4a5c3` |
| A1 | Attendee | a1@kolabing.test | `5\|jNmZggfIvFYGJc1AI5P7Kdp72GRETJJpJLjvbmMqf6216fe7` |
| B1 | Business | b1@kolabing.test | ID: `019ecf76-1663-707c-87da-f1133847977a` pw: `test1234` |
| B2 | Business | b2@kolabing.test | ID: `019ecf7f-ebe2-73dd-af75-2588a481f466` pw: `test1234` |


## Admin Panel

| Account | Email | Password |
|---------|-------|----------|
| Admin (maintainer) | admin@kolabing.test | password123 |

## Usage

Set the token as a header on every protected request:
```
Authorization: Bearer 1|LHx5cogKhQurqOQ2l9Ag0bTVSaR9N0Q3a0Y6ia7m277db10c
```

## Re-login (if tokens expire)

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"c1@kolabing.test","password":"password123"}'
```
