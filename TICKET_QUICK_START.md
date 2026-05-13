# 🚀 Ticket System - Quick Start Guide

## Step 1: Clear Symfony Cache
```powershell
php bin/console cache:clear
```

## Step 2: Create Upload Directory
```powershell
New-Item -ItemType Directory -Force -Path public\uploads\tickets
```

## Step 3: Verify Routes
```powershell
php bin/console debug:router | Select-String "ticket"
```

**Expected routes:**
- `ticket_list` - GET /ticket/list
- `ticket_create` - GET|POST /ticket/create
- `ticket_view` - GET /ticket/{id}
- `ticket_reply` - POST /ticket/{id}/reply
- `ticket_satisfaction` - POST /ticket/{id}/satisfaction
- `admin_ticket_list` - GET /admin/tickets
- `admin_ticket_view` - GET /admin/tickets/{id}
- `admin_ticket_reply` - POST /admin/tickets/{id}/reply
- `admin_ticket_acknowledge` - POST /admin/tickets/{id}/acknowledge
- `admin_ticket_status` - POST /admin/tickets/{id}/status
- `admin_ticket_delete` - POST /admin/tickets/{id}/delete

## Step 4: Start Development Server
```powershell
symfony server:start
# OR
php -S 127.0.0.1:8000 -t public
```

## Step 5: Test as Client/Worker

1. **Login** to your application as a Client or Worker
2. **Look for the blue support button** at the bottom-right corner
3. **Click the button** → popup should slide in from right
4. **Click "Create New Ticket"**
5. **Fill the form:**
   - Title: "Test Ticket" (min 5 chars)
   - Category: Select any
   - Priority: Select any
   - Description: "This is a test message to verify ticket creation" (min 10 chars)
   - Attachment: (optional) Upload a PDF or image
6. **Submit** → You should be redirected to the ticket view page
7. **Add a reply** to your ticket
8. **Verify** messages appear correctly

## Step 6: Test as Admin

1. **Login** as an Admin user
2. **Navigate to** `/admin/tickets`
3. **Verify statistics cards** show correct counts
4. **Click "View"** on any ticket
5. **Click "Acknowledge"** button
6. **Add a reply** and check "Internal Note" checkbox
7. **Verify** internal note has purple background
8. **Change status** to "CLOSED"
9. **Go back to list** → verify filters work

## Step 7: Security Tests

### Test 1: Guest Access
- Logout
- Try to access `/ticket/create` → Should redirect to login
- Try to access `/admin/tickets` → Should redirect to login

### Test 2: Role Restrictions
- Login as Client
- Try to access `/admin/tickets` → Should get 403 Forbidden

### Test 3: Ownership Verification
- Login as Client A (creates ticket #1)
- Logout, login as Client B
- Try to access `/ticket/1` → Should get 404 Not Found

### Test 4: Form Validation
- Try to submit empty form → Should show validation errors
- Try to upload 15MB file → Should show "File too large" error
- Try to upload .exe file → Should show "Invalid MIME type" error

## Troubleshooting

### Support Button Not Visible?
**Check:**
- Are you logged in?
- Open browser console (F12) → Any JavaScript errors?
- View page source → Is `ticket-popup.js` loaded?

### 404 on Ticket Routes?
**Run:**
```powershell
php bin/console cache:clear
php bin/console debug:router
```

### File Upload Fails?
**Check:**
1. Directory exists: `public/uploads/tickets/`
2. PHP upload limits in `php.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

### CSRF Token Error?
**Clear sessions:**
```powershell
Remove-Item -Recurse -Force var\cache\*
```

## Database Sample Data

### Create Test Categories
```sql
INSERT INTO category_tickets (name, description) VALUES
('Technical Issue', 'Technical problems or bugs'),
('Feature Request', 'Request for new features'),
('General Support', 'General questions and support');
```

### Create Test Ticket (Manual)
```sql
INSERT INTO tickets (created_by_id, category_id, title, status, priority, message_count, created_at, last_message_at)
VALUES (1, 1, 'Sample Ticket', 'OPEN', 'HIGH', 1, NOW(), NOW());

INSERT INTO sub_tickets (ticket_id, sender_id, sender_role, message, created_at)
VALUES (LAST_INSERT_ID(), 1, 'CLIENT', 'This is a test message', NOW());
```

## Next Steps

1. ✅ Test all routes (Client, Worker, Admin)
2. ✅ Verify server-side validation works
3. ✅ Test file uploads
4. ✅ Test popup functionality (AJAX)
5. ✅ Test internal notes (admin only)
6. ✅ Test status lifecycle
7. ✅ Test satisfaction rating

## Files Structure
```
src/
├── Controller/
│   ├── TicketController.php .................. Client/Worker routes
│   └── AdminTicketController.php ............. Admin-only routes
├── Form/
│   ├── TicketType.php ........................ Ticket creation form
│   ├── SubTicketType.php ..................... Reply form
│   └── SatisfactionRatingType.php ............ Rating form
├── Repository/
│   ├── TicketRepository.php .................. Custom ticket queries
│   └── SubTicketRepository.php ............... Message queries

templates/
├── ticket/
│   ├── create.html.twig ...................... Create ticket page
│   └── view.html.twig ........................ View ticket page
└── admin/
    └── ticket/
        ├── index.html.twig ................... Admin dashboard
        └── view.html.twig .................... Admin ticket view

public/
└── js/
    └── ticket-popup.js ....................... Popup functionality

config/
└── packages/
    └── security.yaml ......................... Access control rules
```

## API Endpoints (AJAX)

### GET /ticket/list (JSON)
**Response:**
```json
{
  "tickets": [
    {
      "id": 1,
      "title": "Sample Ticket",
      "status": "OPEN",
      "priority": "HIGH",
      "message_count": 3,
      "created_at": "2024-02-06T10:30:00+00:00"
    }
  ]
}
```

## Validation Messages (French)

You can customize validation messages in forms:

```php
#[Assert\NotBlank(message: 'Le titre est obligatoire')]
#[Assert\Length(
    min: 5,
    max: 255,
    minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
    maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
)]
```

---

**🎉 Ready to test!** Start with Step 1 and work through each step sequentially.
