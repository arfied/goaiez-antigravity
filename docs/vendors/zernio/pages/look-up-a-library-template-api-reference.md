# Look up a library template API Reference

Look up a single pre-approved Template Library template by its exact name, to
introspect its structure before importing it. Most importantly it returns the
template's `buttons`: a library template with `URL` / `PHONE_NUMBER` buttons
must be created with a matching `library_template_button_inputs` array (see
Create Template), or Meta rejects it. Use this to discover which inputs to collect.


## GET /v1/whatsapp/template-library

**Look up a library template**

Look up a single pre-approved Template Library template by its exact name, to
introspect its structure before importing it. Most importantly it returns the
template's `buttons`: a library template with `URL` / `PHONE_NUMBER` buttons
must be created with a matching `library_template_button_inputs` array (see
Create Template), or Meta rejects it. Use this to discover which inputs to collect.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **name** (required) in query: Exact library template name
- **language** (optional) in query: Desired language variant (e.g. es, en_US). If the template is not offered in it, the first available variant is returned and named in the response language field.

### Responses

#### 200: Library template (or null if no exact match)

**Response Body:**

- **template** `object,null`: No description

#### 400: Missing or invalid query params

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
