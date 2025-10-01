# Notifications API Documentation

## Table of Contents
- [Save FCM Token](#save-fcm-token)
- [Get Notifications](#get-notifications)
- [Mark Notification as Read](#mark-notification-as-read)
- [Mark All Notifications as Read](#mark-all-notifications-as-read)

## Save FCM Token

Saves or updates the FCM token for the authenticated user's device.

**Endpoint:** `POST /api/save_fcm_token`

### Request
```json
{
    "token": "fcm_token_123456789",
    "device_id": "device_12345",
    "device_info": {
        "os": "Android",
        "browser": "Chrome",
        "version": "12.0"
    }
}
```

### Success Response (200 OK)
```json
{
    "status": true,
    "message": "FCM token saved successfully",
    "data": {
        "id": 1,
        "user_id": 5,
        "user_type": "App\\Models\\Student",
        "token": "fcm_token_123456789",
        "device_id": "device_12345",
        "device_info": {
            "os": "Android",
            "browser": "Chrome",
            "version": "12.0"
        },
        "updated_at": "2025-06-03T10:15:22.000000Z",
        "created_at": "2025-06-03T10:15:22.000000Z"
    }
}
```

### Error Response (422 Unprocessable Entity)
```json
{
    "status": false,
    "message": "The token field is required.",
    "data": null
}
```

## Get Notifications

Retrieves paginated notifications for the authenticated user.

**Endpoint:** `GET /api/notifications`

### Query Parameters
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 15)

### Success Response (200 OK)
```json
{
    "status": true,
    "message": "Notifications retrieved successfully",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "type": "App\\Notifications\\NewMessage",
                "notifiable_type": "App\\Models\\Student",
                "notifiable_id": 5,
                "data": {
                    "title": "New Message",
                    "message": "You have a new message from John Doe",
                    "type": "message",
                    "id": 123
                },
                "read_at": null,
                "created_at": "2025-06-03T10:30:00.000000Z",
                "updated_at": "2025-06-03T10:30:00.000000Z"
            },
            {
                "id": 2,
                "type": "App\\Notifications\\AppointmentReminder",
                "notifiable_type": "App\\Models\\Student",
                "notifiable_id": 5,
                "data": {
                    "title": "Appointment Reminder",
                    "message": "Your appointment is in 1 hour",
                    "type": "appointment",
                    "id": 456
                },
                "read_at": "2025-06-03T10:25:00.000000Z",
                "created_at": "2025-06-03T09:00:00.000000Z",
                "updated_at": "2025-06-03T10:25:00.000000Z"
            }
        ],
        "first_page_url": "http://example.com/api/notifications?page=1",
        "from": 1,
        "last_page": 1,
        "last_page_url": "http://example.com/api/notifications?page=1",
        "links": [
            {
                "url": null,
                "label": "&laquo; Previous",
                "active": false
            },
            {
                "url": "http://example.com/api/notifications?page=1",
                "label": "1",
                "active": true
            },
            {
                "url": null,
                "label": "Next &raquo;",
                "active": false
            }
        ],
        "next_page_url": null,
        "path": "http://example.com/api/notifications",
        "per_page": 10,
        "prev_page_url": null,
        "to": 2,
        "total": 2
    }
}
```

## Mark Notification as Read

Marks a specific notification as read.

**Endpoint:** `POST /api/notifications/mark-as-read/{id}`

### Success Response (200 OK)
```json
{
    "status": true,
    "message": "Notification marked as read",
    "data": null
}
```

## Mark All Notifications as Read

Marks all unread notifications as read for the authenticated user.

**Endpoint:** `POST /api/notifications/mark-as-read`

### Success Response (200 OK)
```json
{
    "status": true,
    "message": "5 notifications marked as read",
    "data": null
}
```

## Error Response (500 Internal Server Error)
```json
{
    "status": false,
    "message": "Error message describing what went wrong",
    "data": null
}
```

## Notes

1. **Authentication**: All endpoints require authentication via Bearer token.
2. **Pagination**: The get notifications endpoint uses Laravel's built-in pagination.
3. **Notification Data**: The `data` field in notifications is a JSON field that can contain any structured data.
4. **User Types**: The system supports multiple user types (Student, Teacher, Academy) which are automatically detected.
5. **Timestamps**: All timestamps are in ISO 8601 format (UTC).
