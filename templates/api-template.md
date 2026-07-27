# API Template

## Endpoint

```text
METHOD /api/v1/resource
```

## Permission

```text
resource.action
```

## Request

```json
{}
```

## Validation

| Field | Rule | Description |
|---|---|---|

## Success

```json
{
  "success": true,
  "message": "",
  "data": {},
  "meta": null,
  "errors": null
}
```

## Errors

- 401
- 403
- 404
- 409
- 422

## Side effects

- Event.
- Notification.
- Audit.
- Queue.

## Idempotency

Nêu rõ có hoặc không.
