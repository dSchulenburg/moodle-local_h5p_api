# Moodle H5P REST API Plugin

This Moodle local plugin provides REST API endpoints for uploading and managing H5P content directly in Moodle's Content Bank.

## Features

- Upload H5P files via REST API (base64 encoded)
- List H5P content in content bank
- Get embed codes for H5P content
- Support for course-specific or system-wide content

## Requirements

- Moodle 4.0+
- H5P content type enabled in Content Bank

## Installation

1. Copy the `local_h5p_api` folder to `/local/h5p_api/` in your Moodle installation
2. Visit Site Administration → Notifications to complete the installation
3. Add the web service functions to your external service

## API Endpoints

### local_h5p_api_upload

Upload H5P content to the content bank.

**Parameters:**
- `base64data` (string, required) - Base64 encoded H5P file
- `filename` (string, optional) - Filename (default: content.h5p)
- `title` (string, optional) - Content title
- `contextid` (int, optional) - Context ID for storage
- `courseid` (int, optional) - Course ID (alternative to contextid)

**Returns:**
```json
{
  "success": true,
  "contentid": 123,
  "name": "My H5P Content",
  "contextid": 1,
  "embedurl": "https://moodle.example.com/h5p/embed.php?url=...",
  "iframecode": "<iframe src=\"...\" width=\"100%\" height=\"600\"></iframe>"
}
```

### local_h5p_api_list

List H5P content in the content bank.

**Parameters:**
- `contextid` (int, optional) - Context ID (0 = system)
- `courseid` (int, optional) - Course ID

**Returns:**
```json
{
  "success": true,
  "count": 5,
  "items": [
    {
      "contentid": 123,
      "name": "My Quiz",
      "contextid": 1,
      "timecreated": 1705312800,
      "timemodified": 1705312800,
      "filename": "quiz.h5p",
      "embedurl": "..."
    }
  ]
}
```

### local_h5p_api_get_embed

Get embed code for specific H5P content.

**Parameters:**
- `contentid` (int, required) - Content bank content ID

**Returns:**
```json
{
  "success": true,
  "contentid": 123,
  "name": "My Quiz",
  "embedurl": "...",
  "iframecode": "<iframe ...></iframe>",
  "filtercode": "{h5p:123}"
}
```

## Usage Example (Python)

```python
import requests
import base64

MOODLE_URL = "https://moodle.example.com"
TOKEN = "your_webservice_token"

# Read and encode H5P file
with open("quiz.h5p", "rb") as f:
    base64_data = base64.b64encode(f.read()).decode()

# Upload to Moodle
response = requests.post(
    f"{MOODLE_URL}/webservice/rest/server.php",
    data={
        "wstoken": TOKEN,
        "wsfunction": "local_h5p_api_upload",
        "moodlewsrestformat": "json",
        "base64data": base64_data,
        "filename": "quiz.h5p",
        "title": "My Quiz",
        "courseid": 8
    }
)

result = response.json()
print(f"Content ID: {result['contentid']}")
print(f"Embed URL: {result['embedurl']}")
```

## Embedding in Moodle

After upload, you can embed H5P content in Moodle pages using:

1. **iframe** (works everywhere):
```html
<iframe src="[embedurl]" width="100%" height="600" frameborder="0" allowfullscreen></iframe>
```

2. **Moodle Filter** (if H5P filter is enabled):
```
{h5p:contentid}
```

## License

GNU GPL v3 or later

## Author

Dirk Schulenburg (2026)
