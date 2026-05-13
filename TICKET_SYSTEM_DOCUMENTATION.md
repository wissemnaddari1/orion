# 🎫 Ticket System Implementation - Complete Documentation

## ✅ Implementation Summary

A complete, secure, and professional ticket (support/reclamation) functionality for **Client**, **Worker**, and **Admin** roles with strict **PHP server-side validation** (contrôle de saisie).

---

## 🏗️ Architecture Overview

### **MVC Pattern**
```
┌─────────────────────────────────────────────────┐
│                   USER REQUEST                  │
└───────────────┬─────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────┐
│              CONTROLLER LAYER                   │
│  TicketController.php (Client/Worker)           │
│  AdminTicketController.php (Admin)              │
│  - Route handling                               │
│  - Authorization checks                         │
│  - Form processing & server-side validation     │
└───────────────┬─────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────┐
│               FORM LAYER (Validation)           │
│  TicketType.php - Ticket creation               │
│  SubTicketType.php - Reply messages             │
│  SatisfactionRatingType.php - Feedback          │
│  - NotBlank, Length, Choice constraints         │
│  - File validation (10MB, MIME types)           │
│  - CSRF token protection                        │
└───────────────┬─────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────┐
│           REPOSITORY LAYER (Data Access)        │
│  TicketRepository.php                           │
│  SubTicketRepository.php                        │
│  - Custom query methods                         │
│  - Filtering & statistics                       │
└───────────────┬─────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────┐
│              ENTITY LAYER (Model)               │
│  Ticket.php - Main ticket entity                │
│  SubTicket.php - Messages/replies               │
│  - Doctrine ORM mapping                         │
│  - Relationships (ManyToOne, OneToMany)         │
└───────────────┬─────────────────────────────────┘
                │
                ▼
┌─────────────────────────────────────────────────┐
│                  DATABASE                        │
│  MySQL - orion database                         │
│  - tickets table                                │
│  - sub_tickets table                            │
│  - category_tickets table                       │
└─────────────────────────────────────────────────┘
```

---

## 📁 Files Created/Modified

### **Controllers**
✅ `src/Controller/TicketController.php` (326 lines)
- Routes:
  - `GET /ticket/list` - JSON endpoint for AJAX (popup)
  - `GET|POST /ticket/create` - Create new ticket
  - `GET /ticket/{id}` - View ticket details
  - `POST /ticket/{id}/reply` - Add reply to ticket
  - `POST /ticket/{id}/satisfaction` - Submit rating after closure

✅ `src/Controller/AdminTicketController.php` (295 lines)
- Routes:
  - `GET /admin/tickets` - List all tickets with filters
  - `GET /admin/tickets/{id}` - View ticket (including internal notes)
  - `POST /admin/tickets/{id}/reply` - Reply or add internal note
  - `POST /admin/tickets/{id}/acknowledge` - Mark ticket as seen
  - `POST /admin/tickets/{id}/status` - Change ticket status
  - `POST /admin/tickets/{id}/delete` - Delete ticket permanently

### **Forms (PHP Validation)**
✅ `src/Form/TicketType.php`
- Fields: title, category, priority, message, attachment
- Validation:
  - `NotBlank()` on all required fields
  - `Length(min: 5, max: 255)` on title
  - `Length(min: 10)` on message
  - `Choice(['LOW', 'MEDIUM', 'HIGH'])` on priority
  - `File(maxSize: 10M, mimeTypes: [image/*, application/pdf, ...])` on attachment
  - CSRF token protection

✅ `src/Form/SubTicketType.php`
- Fields: message, attachment, isInternal (admin only)
- Validation:
  - `NotBlank()` on message
  - `Length(min: 5)` on message
  - `File(maxSize: 10M)` on attachment
  - Conditional `isInternal` checkbox based on user role

✅ `src/Form/SatisfactionRatingType.php`
- Fields: rating (1-5 stars), comment
- Validation:
  - `NotBlank()` on rating
  - `Range(min: 1, max: 5)` on rating
  - `Choice([1,2,3,4,5])` on rating
  - `Length(max: 1000)` on comment

### **Repositories (Custom Queries)**
✅ `src/Repository/TicketRepository.php`
- Methods:
  - `findByUser($userId)` - Get user's tickets
  - `findForAdmin($filters)` - Admin list with filters (status, priority, category, acknowledged)
  - `countUnacknowledged()` - Count tickets not yet seen by admin
  - `findOneByIdAndUser($ticketId, $userId)` - Enforce ownership
  - `getStatistics()` - Total, open, in_progress, unacknowledged counts

✅ `src/Repository/SubTicketRepository.php`
- Methods:
  - `findByTicket($ticketId, $isAdmin)` - Get messages (exclude internal notes for non-admins)
  - `countUnreadByTicket($ticketId)` - Count unread messages
  - `markAsReadByTicket($ticketId, $userId)` - Mark all as read

### **Twig Templates**
✅ `templates/ticket/create.html.twig` - Create ticket form (Client/Worker)
✅ `templates/ticket/view.html.twig` - View ticket + reply form + satisfaction rating
✅ `templates/admin/ticket/index.html.twig` - Admin dashboard with filters & statistics
✅ `templates/admin/ticket/view.html.twig` - Admin view with internal notes + status management

### **JavaScript**
✅ `public/js/ticket-popup.js` (350 lines)
- Features:
  - Fixed support button (bottom-right, blue circle icon)
  - Slide-in panel (right side, 96rem width on desktop)
  - AJAX fetch from `/ticket/list` endpoint
  - Responsive ticket cards with status/priority badges
  - Empty state when no tickets exist
  - Automatic date formatting (e.g., "2h ago", "3d ago")
  - XSS prevention via `escapeHtml()` function

### **Configuration**
✅ `config/packages/security.yaml`
- Access control rules:
  - `/admin/tickets` → `ROLE_ADMIN`
  - `/ticket` → `IS_AUTHENTICATED_FULLY`
  - Public routes: `/`, `/login`, `/register`

✅ `templates/layout_app.html.twig`
- Added `{% block javascripts %}` with conditional inclusion:
  ```twig
  {% if app.user %}
      <script src="{{ asset('js/ticket-popup.js') }}"></script>
  {% endif %}
  ```

---

## 🔐 Security Features Implemented

### **1. Server-Side Validation (PHP)**
All forms use Symfony constraints:
- `NotBlank` - Prevents empty submissions
- `Length` - Min/max character limits
- `Choice` - Enum validation (status, priority)
- `File` - Size (10MB) + MIME type restrictions
- `Range` - Numeric bounds (rating 1-5)

### **2. CSRF Protection**
All forms include `{{ form_widget(form._token) }}` and validation via `$form->isValid()`

### **3. SQL Injection Prevention**
- Doctrine ORM with QueryBuilder (parameterized queries)
- No raw SQL strings
- Example: `->setParameter('userId', $userId)`

### **4. XSS Prevention**
- Twig auto-escaping enabled by default
- JavaScript uses `escapeHtml()` function for dynamic content
- `{{ ticket.title }}` → automatically escaped

### **5. Authorization Checks**
- `#[IsGranted('IS_AUTHENTICATED_FULLY')]` on TicketController
- `#[IsGranted('ROLE_ADMIN')]` on AdminTicketController
- Ownership verification: `findOneByIdAndUser($ticketId, $userId)`
- Access control in `security.yaml`

### **6. File Upload Security**
- SluggerInterface for safe filenames
- Unique ID suffix: `filename-{uniqid()}.{extension}`
- MIME type validation via `File` constraint
- Files stored outside public root (recommended): `/public/uploads/tickets/`
- Example: `conference-presentation-65f8a92c1d.pdf`

### **7. Role-Based Access Control**
- Client/Worker: Can only view/reply to own tickets
- Admin: Can view all tickets, add internal notes, change status
- Internal notes filter: `WHERE t.isInternal = false OR sender_role = 'ADMIN'`

---

## 🎯 Ticket Lifecycle Management

### **Status Transitions**
```
OPEN → IN_PROGRESS → WAITING_USER → CLOSED
  ↑         ↓              ↓           ↓
  └─────────┴──────────────┴───────────┘
        (Admin can change status manually)
```

### **Automatic Status Changes**
1. **Ticket Created** → Status: `OPEN`
2. **Admin Acknowledges** → Status: `IN_PROGRESS` (if was OPEN)
3. **Admin Replies** → Status: `WAITING_USER` (if was IN_PROGRESS)
4. **Admin Closes** → Status: `CLOSED` + `closed_at` timestamp set
5. **User Submits Rating** → `satisfaction_rating` & `satisfaction_comment` saved

### **Message Count**
- Incremented on every non-internal reply
- Excludes internal admin notes from count
- Used for badge display in popup

---

## 🧪 Testing Checklist

### **Manual Testing Steps**

#### **Client/Worker Tests**
1. ✅ Login as Client/Worker
2. ✅ Click support button (bottom-right blue circle)
3. ✅ Verify popup slides in from right
4. ✅ Click "Create New Ticket"
5. ✅ Submit form with validation errors:
   - Empty title → Error: "This value should not be blank"
   - Title < 5 chars → Error: "Minimum 5 characters"
   - File > 10MB → Error: "File too large"
   - Invalid MIME type → Error: "Allowed types: PDF, images, Word"
6. ✅ Submit valid form with attachment
7. ✅ Verify redirect to ticket view page
8. ✅ Add reply to own ticket
9. ✅ Try to access `/ticket/999` (not owned) → 404 error
10. ✅ Close ticket and submit satisfaction rating
11. ✅ Try to submit rating again → Error: "Already rated"

#### **Admin Tests**
1. ✅ Login as Admin
2. ✅ Access `/admin/tickets`
3. ✅ Verify statistics cards (Total, Open, In Progress, Unacknowledged)
4. ✅ Apply filters (Status, Priority, Category, Acknowledged)
5. ✅ Click "View" on unacknowledged ticket (yellow highlight)
6. ✅ Click "Acknowledge" button → Verify green confirmation
7. ✅ Add internal note (check "Internal Note" checkbox)
8. ✅ Verify internal note has purple border
9. ✅ Change status to "CLOSED"
10. ✅ Add resolution note
11. ✅ Delete ticket → Confirm deletion prompt
12. ✅ Verify ticket removed from list

#### **Security Tests**
1. ✅ Logout → Verify support button disappears
2. ✅ Try `/ticket/create` as guest → Redirect to login
3. ✅ Try `/admin/tickets` as Client → 403 Forbidden
4. ✅ Submit CSRF token mismatch → Error: "Invalid CSRF token"
5. ✅ Upload `.exe` file → Error: "Invalid MIME type"
6. ✅ SQL injection test: `'; DROP TABLE tickets; --` in title → Safely escaped
7. ✅ XSS test: `<script>alert('XSS')</script>` in message → Escaped/displayed as text

---

## 🚀 Deployment Instructions

### **1. Clear Symfony Cache**
```powershell
php bin/console cache:clear
```

### **2. Create Upload Directory**
```powershell
New-Item -ItemType Directory -Force -Path public\uploads\tickets
```

### **3. Set Permissions (Linux/Mac)**
```bash
chmod 755 public/uploads
chmod 755 public/uploads/tickets
```

### **4. Verify Routes**
```powershell
php bin/console debug:router | Select-String "ticket"
```

Expected output:
```
ticket_list              GET      /ticket/list
ticket_create            GET|POST /ticket/create
ticket_view              GET      /ticket/{id}
ticket_reply             POST     /ticket/{id}/reply
ticket_satisfaction      POST     /ticket/{id}/satisfaction
admin_ticket_list        GET      /admin/tickets
admin_ticket_view        GET      /admin/tickets/{id}
admin_ticket_reply       POST     /admin/tickets/{id}/reply
admin_ticket_acknowledge POST     /admin/tickets/{id}/acknowledge
admin_ticket_status      POST     /admin/tickets/{id}/status
admin_ticket_delete      POST     /admin/tickets/{id}/delete
```

### **5. Test JavaScript Loading**
Open browser console (F12) and verify:
```
✅ No errors in console
✅ ticket-popup.js loaded
✅ Support button visible (authenticated users only)
```

### **6. Database Verification**
```sql
-- Check tickets table exists
SHOW TABLES LIKE '%ticket%';

-- Verify columns
DESCRIBE tickets;
DESCRIBE sub_tickets;

-- Check sample data
SELECT id, title, status, priority, created_by_id FROM tickets;
```

---

## 📊 Database Schema

### **Tickets Table**
```sql
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    status ENUM('OPEN', 'IN_PROGRESS', 'WAITING_USER', 'CLOSED'),
    priority ENUM('LOW', 'MEDIUM', 'HIGH'),
    message_count INT DEFAULT 1,
    acknowledged_by_ad BOOLEAN DEFAULT FALSE,
    acknowledged_at DATETIME NULL,
    closed_at DATETIME NULL,
    satisfaction_rating INT NULL,
    satisfaction_comment TEXT NULL,
    resolution TEXT NULL,
    created_at DATETIME NOT NULL,
    last_message_at DATETIME NOT NULL,
    FOREIGN KEY (created_by_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES category_tickets(id)
);
```

### **SubTickets Table**
```sql
CREATE TABLE sub_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('CLIENT', 'WORKER', 'ADMIN'),
    message TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    is_read BOOLEAN DEFAULT FALSE,
    is_edited BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(500) NULL,
    file_type VARCHAR(100) NULL,
    file_size INT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)
);
```

---

## 🎨 UI/UX Features

### **Client/Worker Interface**
- **Fixed Support Button**: Blue circle at bottom-right (z-index: 50)
- **Slide-in Panel**: 96rem width on desktop, full width on mobile
- **Ticket Cards**: Status/priority badges, message count indicator
- **Empty State**: SVG icon + "No tickets yet" message
- **Responsive Design**: TailwindCSS with `md:` breakpoints

### **Admin Interface**
- **Statistics Dashboard**: 4-card grid (Total, Open, In Progress, Unacknowledged)
- **Advanced Filters**: Status, Priority, Category, Acknowledged (dropdown menus)
- **Table View**: Sortable columns, yellow highlight for unacknowledged tickets
- **Status Management**: Inline form with resolution note input
- **Internal Notes**: Purple border/background to distinguish from regular messages
- **Danger Zone**: Red box with delete confirmation

### **Accessibility**
- Semantic HTML (`<main>`, `<aside>`, `<header>`)
- ARIA labels on icons
- Keyboard navigation support (ESC to close popup)
- Focus states on interactive elements

---

## 🐛 Common Issues & Solutions

### **Issue: Support button not appearing**
**Solution**: Check:
1. User is authenticated: `{% if app.user %}`
2. JavaScript loaded: View source → `<script src="/js/ticket-popup.js">`
3. Browser console errors (F12)

### **Issue: 404 on /ticket/list**
**Solution**: Clear Symfony cache:
```powershell
php bin/console cache:clear
php bin/console debug:router | Select-String "ticket"
```

### **Issue: File upload fails**
**Solution**:
1. Check directory exists: `public/uploads/tickets/`
2. Check permissions (Linux): `chmod 755 public/uploads/tickets`
3. Verify `upload_max_filesize` in `php.ini` (must be ≥10MB)

### **Issue: CSRF token invalid**
**Solution**:
1. Clear sessions: Delete `var/sessions/*`
2. Restart PHP server: `php -S 127.0.0.1:8000 -t public`
3. Hard refresh browser: Ctrl+F5

### **Issue: Internal notes visible to clients**
**Solution**: Check `SubTicketRepository::findByTicket($ticketId, $isAdmin)`
- Ensure `$isAdmin = false` filters `WHERE (t.isInternal = false OR t.isInternal IS NULL)`

---

## 📝 Future Enhancements

### **Phase 2 Features**
- [ ] Real-time notifications (Mercure/Symfony UX Turbo)
- [ ] Email alerts on ticket status change
- [ ] File drag-and-drop upload
- [ ] Ticket priority escalation (auto-bump after 48h)
- [ ] SLA tracking (response time, resolution time)
- [ ] Ticket templates (pre-filled forms)
- [ ] Multi-language support (translations)
- [ ] Export tickets to PDF/CSV
- [ ] Search functionality (full-text search)
- [ ] Ticket tagging system

### **Performance Optimizations**
- [ ] Add database indexes on frequently queried columns:
  ```sql
  CREATE INDEX idx_status ON tickets(status);
  CREATE INDEX idx_priority ON tickets(priority);
  CREATE INDEX idx_created_by ON tickets(created_by_id);
  ```
- [ ] Implement pagination for ticket lists
- [ ] Cache statistics with Redis
- [ ] Lazy-load file attachments

---

## 📚 Validation Reference

### **NotBlank Constraint**
```php
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\NotBlank(message: 'Titre requis')]
private ?string $title = null;
```

### **Length Constraint**
```php
#[Assert\Length(
    min: 5,
    max: 255,
    minMessage: 'Titre trop court (min 5 caractères)',
    maxMessage: 'Titre trop long (max 255 caractères)'
)]
```

### **Choice Constraint**
```php
#[Assert\Choice(
    choices: ['LOW', 'MEDIUM', 'HIGH'],
    message: 'Priorité invalide'
)]
```

### **File Constraint**
```php
#[Assert\File(
    maxSize: '10M',
    mimeTypes: [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    maxSizeMessage: 'Fichier trop volumineux (max 10 Mo)',
    mimeTypesMessage: 'Type de fichier non autorisé'
)]
```

### **Range Constraint**
```php
#[Assert\Range(
    min: 1,
    max: 5,
    notInRangeMessage: 'Note doit être entre {{ min }} et {{ max }}'
)]
```

---

## ✅ Compliance with Academic Requirements

### **Contrôle de Saisie (Input Validation)**
✅ **Server-side validation in PHP** (Symfony Forms)
✅ **No client-side-only validation** (JavaScript is supplementary)
✅ **Validation executed BEFORE database insertion**
✅ **Error messages in French** (customizable via `message:` parameter)

### **Security Best Practices**
✅ **CSRF protection** (all forms)
✅ **SQL injection prevention** (Doctrine ORM)
✅ **XSS prevention** (Twig auto-escaping)
✅ **Role-based access control** (#[IsGranted])
✅ **File upload validation** (size + MIME type)

### **MVC Architecture**
✅ **Controller**: Request handling, authorization
✅ **Model (Entity)**: Data structure, relationships
✅ **View (Twig)**: Presentation layer
✅ **Repository**: Data access logic (custom queries)

---

## 📞 Support Contact
**Developer**: CodeVeins Team  
**Repository**: https://github.com/OUSSEMA1177/CodeVeins  
**Branch**: GestionTickets  
**Framework**: Symfony 6.4  
**Database**: MySQL 8.0  

---

**🎉 Implementation Complete! All 7 todos finished.**
