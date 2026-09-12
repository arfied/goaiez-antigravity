# Upload media file API Reference

Upload a media file using API key authentication and get back a publicly accessible URL.
The URL can be used as attachmentUrl when sending inbox messages.

Files are stored in temporary storage and auto-delete after 7 days.
Maximum file size is 25MB.

Unlike /v1/media/upload (which uses upload tokens for end-user flows),
this endpoint takes your API key in the Authorization header, for programmatic use.


## POST /v1/media/upload-direct

**Upload media file**

Upload a media file using API key authentication and get back a publicly accessible URL.
The URL can be used as attachmentUrl when sending inbox messages.

Files are stored in temporary storage and auto-delete after 7 days.
Maximum file size is 25MB.

Unlike /v1/media/upload (which uses upload tokens for end-user flows),
this endpoint takes your API key in the Authorization header, for programmatic use.


### Request Body


### Responses

#### 200: File uploaded successfully

**Response Body:**

- **url** `string`: Publicly accessible URL for the uploaded file
- **filename** `string`: Generated unique filename
- **contentType** `string`: MIME type of the file
- **size** `integer`: File size in bytes

#### 400: No file provided or file too large

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
