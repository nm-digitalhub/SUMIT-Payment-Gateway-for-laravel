# CRM System - Complete Implementation Reference

> **Version**: v1.9.0
> **Last Updated**: 2025-12-01
> **Status**: ✅ 100% API Coverage (12/12 endpoints)

This document provides a comprehensive reference for the complete CRM implementation in the SUMIT Payment Gateway package.

---

## Table of Contents

1. [API Coverage](#api-coverage)
2. [Database Schema](#database-schema)
3. [Models Reference](#models-reference)
4. [Services Reference](#services-reference)
5. [Filament Resources](#filament-resources)
6. [Implementation Status](#implementation-status)

---

## API Coverage

### ✅ 100% Complete (12/12 Endpoints)

#### CRM Data API (9/9)
| Endpoint | Method | Service Method | Status |
|----------|--------|----------------|--------|
| `/creditguy/crm/data/folders/` | GET | `listFolders()` | ✅ |
| `/creditguy/crm/data/folder/{id}` | GET | `getFolder($folderId)` | ✅ |
| `/creditguy/crm/data/{folder_id}/entities/` | GET | `listEntities($folderId)` | ✅ |
| `/creditguy/crm/data/entity/{id}` | GET | `getEntity($entityId)` | ✅ |
| `/creditguy/crm/data/entity/create/` | POST | `createEntity($folderId, $data)` | ✅ |
| `/creditguy/crm/data/entity/update/` | POST | `updateEntity($entityId, $data)` | ✅ |
| `/creditguy/crm/data/entity/archive/` | POST | `archiveEntity($entityId)` | ✅ |
| `/creditguy/crm/data/entity/delete/` | DELETE | `deleteEntity($entityId)` | ✅ |
| `/creditguy/crm/data/entity/usage/` | GET | `countEntityUsage($entityId)` | ✅ |

#### CRM Schema API (2/2)
| Endpoint | Method | Service Method | Status |
|----------|--------|----------------|--------|
| `/creditguy/crm/schema/folder/{id}` | GET | `getFolderSchema($folderId)` | ✅ |
| `/creditguy/crm/schema/fields/{folder_id}` | GET | `getFieldDefinitions($folderId)` | ✅ |

#### CRM Views API (1/1)
| Endpoint | Method | Service Method | Status |
|----------|--------|----------------|--------|
| `/creditguy/crm/views/{folder_id}` | GET | `listViews($folderId)` | ✅ |

---

## Database Schema

### 7 CRM Tables

```
officeguy_crm_folders           # Entity types (Contacts, Leads, Companies, Deals)
officeguy_crm_folder_fields     # Field definitions (schema)
officeguy_crm_entities          # Main entity data (all types)
officeguy_crm_entity_fields     # Dynamic field values
officeguy_crm_entity_relations  # Entity relationships
officeguy_crm_activities        # Activity tracking (calls, emails, meetings)
officeguy_crm_views             # Saved views/filters
```

### Entity Relationship Diagram

```
CrmFolder (1) ──┬─── (N) CrmFolderField
                │
                └─── (N) CrmEntity ──┬─── (N) CrmEntityField ───→ (1) CrmFolderField
                                     │
                                     ├─── (N) CrmActivity
                                     │
                                     ├─── (1) User (owner)
                                     │
                                     ├─── (1) User (assigned)
                                     │
                                     └─── (N) CrmEntityRelation ───→ (1) CrmEntity

CrmFolder (1) ──── (N) CrmView
```

---

## Models Reference

### 1. CrmEntity (Main Entity Model)

**Table**: `officeguy_crm_entities`
**Purpose**: Stores all CRM entities (contacts, leads, companies, deals)
**Soft Deletes**: ✅ Yes

#### Fields (26 fields)

**Core Fields**:
- `id` (int, PK)
- `crm_folder_id` (int, FK → officeguy_crm_folders)
- `sumit_entity_id` (int, nullable) - SUMIT CRM entity ID
- `entity_type` (string) - contact, lead, company, deal
- `name` (string) - Full name / Company name

**Contact Information**:
- `email` (string, nullable)
- `phone` (string, nullable)
- `mobile` (string, nullable)

**Address**:
- `address` (string, nullable)
- `city` (string, nullable)
- `state` (string, nullable)
- `postal_code` (string, nullable)
- `country` (string, default: 'IL')

**Business**:
- `company_name` (string, nullable) - For contacts
- `tax_id` (string, nullable) - Tax ID / VAT number

**Metadata**:
- `status` (string) - active, inactive, archived
- `source` (string, nullable) - website, referral, import, manual
- `owner_user_id` (int, nullable, FK → users)
- `assigned_to_user_id` (int, nullable, FK → users)
- `sumit_customer_id` (int, nullable)
- `last_contact_at` (datetime, nullable)

**Timestamps**:
- `created_at` (datetime)
- `updated_at` (datetime)
- `deleted_at` (datetime, nullable) - Soft delete

#### Relationships

```php
// BelongsTo
folder() → CrmFolder                  // Entity type/folder
owner() → User                        // Owner user
assigned() → User                     // Assigned to user

// HasMany
customFields() → CrmEntityField[]     // Dynamic field values
activities() → CrmActivity[]          // Activity history
relatedFrom() → CrmEntityRelation[]   // Relations from this entity
relatedTo() → CrmEntityRelation[]     // Relations to this entity
```

#### Scopes

```php
CrmEntity::active()                          // status = 'active'
CrmEntity::ofType('contact')                 // entity_type = 'contact'
CrmEntity::ownedBy($userId)                  // owner_user_id = $userId
CrmEntity::assignedTo($userId)               // assigned_to_user_id = $userId
CrmEntity::search('john@example.com')        // Search name, email, phone, company
```

#### Methods

```php
// Get display name (includes company for contacts)
$entity->display_name                        // "John Doe (Acme Inc)"

// Get custom field value
$entity->getCustomField('industry')          // Returns value based on field type

// Set custom field value
$entity->setCustomField('industry', 'Tech')  // Saves to crm_entity_fields
```

#### Casts

```php
'crm_folder_id' => 'integer'
'sumit_entity_id' => 'integer'
'owner_user_id' => 'integer'
'assigned_to_user_id' => 'integer'
'sumit_customer_id' => 'integer'
'last_contact_at' => 'datetime'
```

---

### 2. CrmFolder (Entity Type Definition)

**Table**: `officeguy_crm_folders`
**Purpose**: Defines entity types (Contacts, Leads, Companies, Deals)
**Soft Deletes**: ❌ No

#### Fields (11 fields)

- `id` (int, PK)
- `sumit_folder_id` (int, nullable) - SUMIT folder ID
- `name` (string) - Singular name (e.g., "Contact")
- `name_plural` (string) - Plural name (e.g., "Contacts")
- `icon` (string, nullable) - Icon name
- `color` (string, nullable) - Hex color code
- `entity_type` (string) - contact, lead, company, deal
- `is_system` (boolean) - System folder (cannot be deleted)
- `is_active` (boolean) - Is folder active
- `settings` (json, nullable) - Folder settings
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// HasMany
fields() → CrmFolderField[]    // Field definitions
entities() → CrmEntity[]       // Entities in this folder
views() → CrmView[]            // Saved views for this folder
```

#### Scopes

```php
CrmFolder::active()                  // is_active = true
CrmFolder::system()                  // is_system = true
CrmFolder::ofType('contact')         // entity_type = 'contact'
```

#### Casts

```php
'sumit_folder_id' => 'integer'
'is_system' => 'boolean'
'is_active' => 'boolean'
'settings' => 'array'
```

---

### 3. CrmFolderField (Field Definition)

**Table**: `officeguy_crm_folder_fields`
**Purpose**: Defines custom fields for each folder type
**Soft Deletes**: ❌ No

#### Fields (14 fields)

- `id` (int, PK)
- `crm_folder_id` (int, FK → officeguy_crm_folders)
- `sumit_field_id` (int, nullable) - SUMIT field ID
- `name` (string) - Field name (snake_case)
- `label` (string) - Display label
- `field_type` (string) - text, number, email, phone, date, select, multiselect, boolean
- `is_required` (boolean)
- `is_unique` (boolean)
- `is_searchable` (boolean)
- `default_value` (string, nullable)
- `validation_rules` (json, nullable) - Laravel validation rules
- `options` (json, nullable) - Options for select/multiselect
- `display_order` (int)
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// BelongsTo
folder() → CrmFolder               // Parent folder

// HasMany
entityFields() → CrmEntityField[]  // Field values for all entities
```

#### Scopes

```php
CrmFolderField::required()         // is_required = true
CrmFolderField::searchable()       // is_searchable = true
CrmFolderField::ordered()          // ORDER BY display_order
```

#### Casts

```php
'crm_folder_id' => 'integer'
'sumit_field_id' => 'integer'
'is_required' => 'boolean'
'is_unique' => 'boolean'
'is_searchable' => 'boolean'
'validation_rules' => 'array'
'options' => 'array'
'display_order' => 'integer'
```

---

### 4. CrmEntityField (Field Value)

**Table**: `officeguy_crm_entity_fields`
**Purpose**: Stores dynamic field values for entities
**Soft Deletes**: ❌ No

#### Fields (8 fields)

- `id` (int, PK)
- `crm_entity_id` (int, FK → officeguy_crm_entities)
- `crm_folder_field_id` (int, FK → officeguy_crm_folder_fields)
- `value` (text, nullable) - Text value
- `value_numeric` (decimal(15,2), nullable) - Numeric value
- `value_date` (date, nullable) - Date value
- `value_boolean` (boolean, nullable) - Boolean value
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// BelongsTo
entity() → CrmEntity               // Parent entity
folderField() → CrmFolderField     // Field definition
```

#### Methods

```php
// Get value based on field type
$entityField->getValue()           // Returns appropriate value column
```

#### Casts

```php
'crm_entity_id' => 'integer'
'crm_folder_field_id' => 'integer'
'value_numeric' => 'decimal:2'
'value_date' => 'date'
'value_boolean' => 'boolean'
```

---

### 5. CrmEntityRelation (Entity Relationships)

**Table**: `officeguy_crm_entity_relations`
**Purpose**: Links entities together (parent/child, related, merged)
**Soft Deletes**: ❌ No

#### Fields (6 fields)

- `id` (int, PK)
- `from_entity_id` (int, FK → officeguy_crm_entities)
- `to_entity_id` (int, FK → officeguy_crm_entities)
- `relation_type` (string) - parent, child, related, duplicate, merged
- `metadata` (json, nullable) - Additional relation data
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// BelongsTo
fromEntity() → CrmEntity           // Source entity
toEntity() → CrmEntity             // Target entity
```

#### Scopes

```php
CrmEntityRelation::ofType('parent')  // relation_type = 'parent'
```

#### Casts

```php
'from_entity_id' => 'integer'
'to_entity_id' => 'integer'
'metadata' => 'array'
```

---

### 6. CrmActivity (Activity Tracking)

**Table**: `officeguy_crm_activities`
**Purpose**: Tracks activities for entities (calls, emails, meetings)
**Soft Deletes**: ❌ No

#### Fields (13 fields)

- `id` (int, PK)
- `crm_entity_id` (int, FK → officeguy_crm_entities)
- `user_id` (int, nullable, FK → users)
- `activity_type` (string) - call, email, meeting, note, task, sms, whatsapp
- `subject` (string)
- `description` (text, nullable)
- `status` (string) - planned, in_progress, completed, cancelled
- `priority` (string) - low, normal, high, urgent
- `start_at` (datetime, nullable)
- `end_at` (datetime, nullable)
- `reminder_at` (datetime, nullable)
- `related_document_id` (int, nullable, FK → officeguy_documents)
- `related_ticket_id` (int, nullable)
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// BelongsTo
entity() → CrmEntity               // Related entity
document() → OfficeGuyDocument     // Related document (invoice, etc.)
```

#### Scopes

```php
CrmActivity::ofType('call')              // activity_type = 'call'
CrmActivity::withStatus('completed')     // status = 'completed'
CrmActivity::completed()                 // status = 'completed'
CrmActivity::planned()                   // status = 'planned'
CrmActivity::forUser($userId)            // user_id = $userId
CrmActivity::upcoming()                  // planned + start_at >= now
CrmActivity::overdue()                   // planned + start_at < now
```

#### Methods

```php
// Check activity state
$activity->isOverdue()             // planned + past due date
$activity->isUpcoming()            // planned + future date
```

#### Casts

```php
'crm_entity_id' => 'integer'
'user_id' => 'integer'
'related_document_id' => 'integer'
'related_ticket_id' => 'integer'
'start_at' => 'datetime'
'end_at' => 'datetime'
'reminder_at' => 'datetime'
```

---

### 7. CrmView (Saved Views)

**Table**: `officeguy_crm_views`
**Purpose**: Stores saved filters and column configurations
**Soft Deletes**: ❌ No

#### Fields (11 fields)

- `id` (int, PK)
- `crm_folder_id` (int, FK → officeguy_crm_folders)
- `sumit_view_id` (int, nullable) - SUMIT view ID
- `name` (string) - View name
- `is_default` (boolean) - Default view for folder
- `is_public` (boolean) - Public view (all users)
- `user_id` (int, nullable, FK → users) - Owner (NULL if public)
- `filters` (json, nullable) - Filter conditions
- `sort_by` (string, nullable) - Sort field
- `sort_direction` (string) - asc, desc
- `columns` (json, nullable) - Visible columns
- `created_at` (datetime)
- `updated_at` (datetime)

#### Relationships

```php
// BelongsTo
folder() → CrmFolder               // Parent folder
```

#### Scopes

```php
CrmView::public()                  // is_public = true
CrmView::default()                 // is_default = true
CrmView::forUser($userId)          // is_public = true OR user_id = $userId
```

#### Methods

```php
// Apply view filters to a query
$view->applyToQuery($query)        // Applies filters, sorting, columns
```

#### Casts

```php
'crm_folder_id' => 'integer'
'sumit_view_id' => 'integer'
'is_default' => 'boolean'
'is_public' => 'boolean'
'user_id' => 'integer'
'filters' => 'array'
'columns' => 'array'
```

---

## Services Reference

### 1. CrmDataService (9 Methods)

**File**: `src/Services/CrmDataService.php`
**Purpose**: CRM data operations (CRUD, sync, export)

```php
// Folder Operations
public static function syncAllFolders(): array
public static function listFolders(): array
public static function getFolder(int $folderId): array

// Entity CRUD Operations
public static function listEntities(int $folderId, array $filters = []): array
public static function getEntity(int $entityId): array
public static function createEntity(int $folderId, array $data): array
public static function updateEntity(int $entityId, array $data): array
public static function deleteEntity(int $entityId): array
public static function archiveEntity(int $entityId): array

// Entity Information
public static function countEntityUsage(int $entityId): array

// Export Operations (HTML/PDF)
public static function getEntityPrintHTML(int $entityId, int $folderId, bool $pdf = false): array
public static function getEntitiesHTML(int $folderId, int $viewId, bool $pdf = false): array

// Sync Operations
public static function syncEntityFromSumit(int $sumitEntityId): CrmEntity
public static function syncAllEntities(?int $folderId = null): array
```

---

### 2. CrmSchemaService (2+ Methods)

**File**: `src/Services/CrmSchemaService.php`
**Purpose**: Schema management (field definitions)

```php
// Schema Operations
public static function getFolderSchema(int $folderId): array
public static function getFieldDefinitions(int $folderId): array

// Sync Operations
public static function syncFolderSchema(int $folderId): CrmFolder
```

---

### 3. CrmViewService (4 Methods)

**File**: `src/Services/CrmViewService.php`
**Purpose**: View management (saved filters)

```php
// View Operations
public static function listViews(int $folderId): array

// Sync Operations
public static function syncViewFromSumit(int $sumitViewId): CrmView
public static function syncAllViews(?int $folderId = null): array
public static function syncAllFoldersViews(): array
```

---

## Filament Resources

### 1. CrmEntityResource (Main Resource)

**Location**: `src/Filament/Resources/CrmEntities/CrmEntityResource.php`

#### Pages
- **List**: `ListCrmEntities.php` - Table with 11 actions
- **Create**: `CreateCrmEntity.php` - Create form
- **Edit**: `EditCrmEntity.php` - Edit form

#### Form Schema (`CrmEntityForm.php`)

**Sections**:
1. **Entity Information** - name, email, phone, mobile
2. **Address** - address, city, state, postal_code, country
3. **Business Details** - company_name, tax_id, status, source
4. **Assignment** - owner_user_id, assigned_to_user_id
5. **Metadata** - last_contact_at, sumit_entity_id, sumit_customer_id

#### Table Configuration (`CrmEntitiesTable.php`)

**Columns** (11 columns):
- sumit_entity_id - Entity ID
- name - Name (semibold)
- folder.name - Folder badge (colored)
- owner.name - Owner (toggleable)
- assigned.name - Assigned To (toggleable)
- activities_count - Activities (count)
- created_at - Created (toggleable, hidden by default)
- updated_at - Updated (since, toggleable)
- deleted_at - Active (icon column, toggleable)

**Filters** (4 filters):
- crm_folder_id - Folder (select, multiple)
- owner_user_id - Owner (select, multiple)
- assigned_user_id - Assigned To (select, multiple)
- TrashedFilter - Include trashed

**Record Actions** (6 actions):
1. **View** - View entity details
2. **Edit** - Edit entity
3. **Sync from SUMIT** - Fetch latest data from SUMIT CRM
4. **Archive** - Soft delete in SUMIT (restorable)
5. **Export PDF** - Download entity as PDF
6. **Check Usage** - Count references before deletion

**Header Actions** (2 actions):
1. **Export All as PDF** - Export all entities in view as PDF
2. **Sync All from SUMIT** - Sync all entities from SUMIT

**Bulk Actions** (3 actions):
1. **Delete Selected** - Delete multiple entities
2. **Archive Selected** - Archive multiple entities
3. **Sync Selected** - Sync multiple entities from SUMIT

**Default Sort**: `updated_at DESC`
**Instant Filtering**: ✅ Yes (`deferFilters(false)`)

---

### 2. CrmFolderResource

**Location**: `src/Filament/Resources/CrmFolders/CrmFolderResource.php`

#### Pages
- **List**: `ListCrmFolders.php` - Folder management
- **Create**: `CreateCrmFolder.php` - Create folder
- **Edit**: `EditCrmFolder.php` - Edit folder

#### Form Schema (`CrmFolderForm.php`)

**Sections**:
1. **Folder Information** - name, name_plural, entity_type
2. **Appearance** - icon, color
3. **Settings** - is_active, is_system
4. **SUMIT Integration** - sumit_folder_id

#### Table Configuration (`CrmFoldersTable.php`)

**Columns**:
- name - Folder Name
- entity_type - Type (badge)
- is_active - Active (boolean)
- is_system - System (boolean)

**Record Actions**:
1. **View** - View folder details
2. **Sync Schema** - Sync field definitions from SUMIT
3. **Sync Entities** - Sync all entities in folder

---

### 3. CrmActivityResource

**Location**: `src/Filament/Resources/CrmActivities/CrmActivityResource.php`

#### Pages
- **List**: `ListCrmActivities.php` - Activity list
- **View**: `ViewCrmActivity.php` - Activity details (read-only)

#### Form Schema (`CrmActivityForm.php`)

**Sections**:
1. **Activity Details** - activity_type, subject, status, priority
2. **Description** - description (markdown)
3. **Timing** - start_at, end_at, reminder_at
4. **Related** - crm_entity_id, related_document_id, related_ticket_id

#### Table Configuration (`CrmActivitiesTable.php`)

**Columns**:
- activity_type - Type (badge, colored)
- subject - Subject (truncated to 50 chars)
- entity.name - Related To
- createdBy.name - Created By (toggleable)
- activity_date - Activity Date (since)
- created_at - Created (toggleable, hidden by default)

**Filters**:
- activity_type - Type (select, multiple)
- crm_entity_id - Related Entity (select, multiple, searchable)

**Record Actions**:
1. **View** - View activity details (Infolist)

**Default Sort**: `activity_date DESC`
**Instant Filtering**: ✅ Yes (`deferFilters(false)`)

#### Infolist Schema (`CrmActivityInfolist.php`)

**Sections**:
1. **Activity Details** - type, subject, date, entity, created by, related items
2. **Description** - Markdown content
3. **Metadata** - Additional data (collapsed)
4. **Timestamps** - created_at, updated_at (collapsed)

---

## Implementation Status

### ✅ Completed Features

#### API Integration
- [x] All 12 SUMIT CRM API endpoints implemented
- [x] CrmDataService (9 methods)
- [x] CrmSchemaService (2 methods + helpers)
- [x] CrmViewService (4 methods)

#### Database Schema
- [x] 7 CRM tables with migrations
- [x] All relationships defined
- [x] Proper indexing on foreign keys
- [x] Soft deletes on CrmEntity

#### Models
- [x] 7 CRM models with full PHPDoc
- [x] All relationships defined with proper type hints
- [x] Custom scopes for common queries
- [x] Helper methods (getCustomField, setCustomField, etc.)
- [x] Attribute accessors (display_name, etc.)

#### Filament Resources
- [x] CrmEntityResource - Full CRUD + 11 actions
- [x] CrmFolderResource - Schema management + sync
- [x] CrmActivityResource - Read-only with Infolist
- [x] All forms with proper validation
- [x] All tables with filters and sorting
- [x] Instant filtering enabled

#### Actions
- [x] Sync from SUMIT (single + bulk + all)
- [x] Archive entities (single + bulk)
- [x] Export PDF (single + all)
- [x] Check usage count
- [x] Schema sync
- [x] Entity sync

### ⏳ Pending Features

#### Testing
- [ ] Unit tests for all services
- [ ] Integration tests for API calls
- [ ] Filament resource tests
- [ ] Model relationship tests

#### Documentation
- [ ] API endpoint examples
- [ ] Service usage examples
- [ ] Custom field usage guide
- [ ] View management guide

#### Future Enhancements
- [ ] Activity creation/editing (currently read-only)
- [ ] Entity relationship management UI
- [ ] Custom field editor in Filament
- [ ] Advanced filtering (custom field values)
- [ ] Bulk import/export
- [ ] Entity merge functionality
- [ ] Duplicate detection
- [ ] Activity reminders
- [ ] Activity calendar view

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| v1.9.0 | 2025-12-01 | ✅ 100% CRM API coverage, all 11 entity actions |
| v1.8.0 | 2025-11-30 | Added CRM schema & view sync |
| v1.7.0 | 2025-11-29 | Initial CRM models & migrations |

---

## Support & Maintenance

**Package**: `officeguy/laravel-sumit-gateway`
**GitHub**: https://github.com/nm-digitalhub/SUMIT-Payment-Gateway-for-laravel
**Maintained By**: NM-DigitalHub
**Email**: info@nm-digitalhub.com

---

**Last Generated**: 2025-12-01 by Claude Code
**Generator Version**: Sonnet 4.5
