# SUMIT CRM API - Complete Mapping

**Package**: `officeguy/laravel-sumit-gateway`  
**Generated**: 2025-12-01 12:56:05  
**Source**: `sumit-openapi.json`

> Complete mapping of all SUMIT CRM API endpoints and their request/response schemas.

---

## Table of Contents

1. [CRM Data Endpoints](#1-crm-data-endpoints-crmdatastar) (9 endpoints)
   - [Create Entity](#post-crmdatacreateentity)
   - [Update Entity](#post-crmdataupdateentity)
   - [Archive Entity](#post-crmdataarchiveentity)
   - [Delete Entity](#post-crmdatadeleteentity)
   - [List Entities](#post-crmdatalistentities)
   - [Get Entity](#post-crmdatagetentity)
   - [Count Entity Usage](#post-crmdatacountentityusage)
   - [Get Entity Print HTML](#post-crmdatagetentityprinthtml)
   - [Get Entities HTML](#post-crmdatagetentitieshtml)

2. [CRM Schema Endpoints](#2-crm-schema-endpoints-crmschemastar) (2 endpoints)
   - [Get Folder](#post-crmschemagetfolder)
   - [List Folders](#post-crmschemalistfolders)

3. [CRM Views Endpoints](#3-crm-views-endpoints-crmviewsstar) (1 endpoint)
   - [List Views](#post-crmviewslistviews)

4. [Common CRM Schemas](#4-common-crm-schemas)
   - [Folder Schema](#officeguyappscrmm vcapitypedfolder)
   - [Property Schema](#officeguyappscrmmvcapitypedproperty)
   - [View Schema](#officeguyappscrmmvcapitypedview)

---

## Quick Reference

### Base URL
```
https://api.sumit.co.il
```

### Authentication
All endpoints require `Credentials` object:
```json
{
  "Credentials": {
    "CompanyID": "YOUR_COMPANY_ID",
    "APIKey": "YOUR_API_KEY"
  }
}
```

### Headers
```
Content-Type: application/json
Content-Language: he (optional, defaults to Hebrew)
User-Agent: Laravel/12.0 SUMIT-Gateway/1.0
```

---

## 1. CRM Data Endpoints (/crm/data/*)

### POST /crm/data/createentity/

**Summary**: Create entity

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_CreateEntity_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "Entity"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "Entity": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_Typed.Entity"
        }
      ],
      "description": "The entity to be created"
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.DataController_Data_CreateEntity_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.DataController_Data_CreateEntity_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/updateentity/

**Summary**: Update entity

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_UpdateEntity_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "Entity"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "Entity": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_Typed.Entity"
        }
      ],
      "description": "The entity to be updated"
    },
    "CreateIfMissing": {
      "type": "boolean",
      "description": "Should the update create the entity if it doesn't exist<div><i>This can only be used when Entity.ID is 0\nDefaults to false.</i></div>",
      "nullable": true
    },
    "RemoveExistingProperties": {
      "type": "boolean",
      "description": "Should the update entity remove all existing properties, which didn't pass through the Entity.Properties list?<div><i>Defaults to false</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.DataController_Data_UpdateEntity_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.DataController_Data_UpdateEntity_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/archiveentity/

**Summary**: Archive entity

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_ArchiveEntity_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "EntityID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "EntityID": {
      "type": "integer",
      "description": "Entity identifier",
      "format": "int64"
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Core_APIEmptyResponse`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/deleteentity/

**Summary**: Delete entity

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_DeleteEntity_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "EntityID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "EntityID": {
      "type": "integer",
      "description": "Entity identifier",
      "format": "int64"
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Core_APIEmptyResponse`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/listentities/

**Summary**: List entities

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_ListEntities_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "Folder": {
      "type": "string",
      "description": "Folder identifier.<div><i>Please note this field is required.\nCan be either application folder name, or FolderID.</i></div>",
      "nullable": true
    },
    "IncludeInheritedFolders": {
      "type": "boolean",
      "description": "Include entities from inherited folders",
      "nullable": true
    },
    "Filters": {
      "type": "array",
      "items": {
        "$ref": "#/components/schemas/Core_Typed.Filter"
      },
      "description": "List filters",
      "nullable": true
    },
    "Order": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_Typed.Order"
        }
      ],
      "description": "List results order (sort)",
      "nullable": true
    },
    "Paging": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_Typed.Paging"
        }
      ],
      "description": "List paging",
      "nullable": true
    },
    "LoadProperties": {
      "type": "boolean",
      "description": "Load results properties<div><i>Defaults to false</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.DataController_Data_ListEntities_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.DataController_Data_ListEntities_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/getentity/

**Summary**: Get entity

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_GetEntity_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "EntityID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "EntityID": {
      "type": "integer",
      "description": "Entities identifier",
      "format": "int64"
    },
    "IncludeIncomingProperties": {
      "type": "boolean",
      "description": "Include incoming entity properties<div><i>Defaults to False</i></div>",
      "nullable": true
    },
    "IncludeFields": {
      "type": "boolean",
      "description": "Include all entity fields<div><i>Defaults to True</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.DataController_Data_GetEntity_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.DataController_Data_GetEntity_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/countentityusage/

**Summary**: Count entity usage

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_CountEntityUsage_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "EntityID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "EntityID": {
      "type": "integer",
      "description": "Entity identifier",
      "format": "int64"
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_System.Int64`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "type": "integer",
      "description": "API specific response data",
      "format": "int64"
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/getentityprinthtml/

**Summary**: Get entity HTML contents for print

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_GetEntityPrintHTML_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "EntityID",
    "SchemaID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "SchemaID": {
      "type": "integer",
      "description": "Schema identifier",
      "format": "int64"
    },
    "EntityID": {
      "type": "integer",
      "description": "Entity identifier",
      "format": "int64"
    },
    "PDF": {
      "type": "boolean",
      "description": "Get PDF instead of HTML<div><i>Defaults to False</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/data/getentitieshtml/

**Summary**: Get entities HTML contents for print

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.DataController_Data_GetEntitiesHTML_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "SchemaID",
    "ViewID"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "SchemaID": {
      "type": "integer",
      "description": "Schema identifier",
      "format": "int64"
    },
    "ViewID": {
      "type": "integer",
      "description": "View identifier",
      "format": "int64"
    },
    "PDF": {
      "type": "boolean",
      "description": "Get PDF instead of HTML<div><i>Defaults to False</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

## 2. CRM Schema Endpoints (/crm/schema/*)

### POST /crm/schema/getfolder/

**Summary**: Get folder details

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_GetFolder_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials",
    "Folder"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "Folder": {
      "minLength": 1,
      "type": "string",
      "description": "Folder identifier.<div><i>Can be either application folder name, or FolderID.</i></div>"
    },
    "IncludeProperties": {
      "type": "boolean",
      "description": "Get folder properties<div><i>Defaults to False</i></div>",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_GetFolder_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_GetFolder_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

### POST /crm/schema/listfolders/

**Summary**: List folders

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_ListFolders_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "NameFilter": {
      "type": "string",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_ListFolders_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.SchemaController_Schema_ListFolders_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

## 3. CRM Views Endpoints (/crm/views/*)

### POST /crm/views/listviews/

**Summary**: List views

**Request Schema**: `OfficeGuy.Apps.CRM.MVC.API.ViewsController_Views_ListViews_Request`

<details>
<summary>Request Structure</summary>

```json
{
  "required": [
    "Credentials"
  ],
  "type": "object",
  "properties": {
    "Credentials": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Core_APICredentials"
        }
      ],
      "description": "Company API credentials"
    },
    "FolderID": {
      "type": "integer",
      "format": "int64"
    }
  },
  "additionalProperties": false
}
```

</details>

**Response Schema**: `Response_OfficeGuy.Apps.CRM.MVC.API.ViewsController_Views_ListViews_Response`

<details>
<summary>Response Structure</summary>

```json
{
  "type": "object",
  "properties": {
    "Status": {
      "allOf": [
        {
          "$ref": "#/components/schemas/Teva.Common.ResponseStatus"
        }
      ],
      "description": "Response status"
    },
    "UserErrorMessage": {
      "type": "string",
      "description": "Error message, in a user readable format",
      "nullable": true
    },
    "TechnicalErrorDetails": {
      "type": "string",
      "description": "Technical error details, let us know if you received this.",
      "nullable": true
    },
    "Data": {
      "allOf": [
        {
          "$ref": "#/components/schemas/OfficeGuy.Apps.CRM.MVC.API.ViewsController_Views_ListViews_Response"
        }
      ],
      "description": "API specific response data",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

</details>

---

## 4. Common CRM Schemas

### OfficeGuy.Apps.CRM.MVC.API.Typed.Folder

```json
{
  "type": "object",
  "properties": {
    "ID": {
      "type": "integer",
      "format": "int64"
    },
    "Name": {
      "type": "string",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

### OfficeGuy.Apps.CRM.MVC.API.Typed.Property

```json
{
  "type": "object",
  "properties": {
    "ID": {
      "type": "integer",
      "format": "int64"
    },
    "Name": {
      "type": "string",
      "nullable": true
    },
    "Description": {
      "type": "string",
      "nullable": true
    },
    "Category": {
      "type": "string",
      "nullable": true
    },
    "ValueType": {
      "type": "string",
      "nullable": true
    },
    "APIName": {
      "type": "string",
      "nullable": true
    },
    "Required": {
      "type": "boolean"
    }
  },
  "additionalProperties": false
}
```

### OfficeGuy.Apps.CRM.MVC.API.Typed.View

```json
{
  "type": "object",
  "properties": {
    "ID": {
      "type": "integer",
      "format": "int64"
    },
    "Name": {
      "type": "string",
      "nullable": true
    }
  },
  "additionalProperties": false
}
```

