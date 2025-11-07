# Journal API Example (Updated Fields)

## 📝 **Updated Journal Fields**

Based on your form, the journal now includes these fields:

### Required Fields:

-   `title` - Journal title
-   `description` - Journal description (replaces content as main field)
-   `journal_pdf` - PDF file (required)

### Optional Fields:

-   `publication_date` - Publication date
-   `category` - Journal category
-   `cover_image` - Cover image file
-   `featured_image` - Featured image file
-   `content` - Additional content (optional)
-   `excerpt` - Short excerpt
-   `status` - draft or published
-   `metadata` - Additional metadata

### Removed Fields:

-   ❌ `author` - No longer needed
-   ❌ `tags` - No longer needed

## 🚀 **API Examples**

### 1. **Create Journal (FormData)**

```javascript
// Create FormData for journal creation
const formData = new FormData();

// Required fields
formData.append("title", "Sustainable Development in Education");
formData.append(
    "description",
    "Exploring sustainable practices in educational institutions and their long-term impact on communities."
);
formData.append("journal_pdf", pdfFile); // File object

// Optional fields
formData.append("publication_date", "06/15/2024");
formData.append("category", "research");
formData.append("cover_image", coverImageFile); // File object
formData.append("featured_image", featuredImageFile); // File object
formData.append("status", "draft"); // or 'published'
formData.append("content", "Additional detailed content here...");
formData.append("excerpt", "Short excerpt for preview...");

// Send request
const response = await fetch("http://127.0.0.1:8000/api/v1/admin/journals", {
    method: "POST",
    headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json",
    },
    body: formData,
});

const result = await response.json();
console.log("Journal created:", result);
```

### 2. **Update Journal**

```javascript
// Update journal with new data
const updateData = new FormData();
updateData.append("title", "Updated Title");
updateData.append("description", "Updated description...");
updateData.append("status", "published");

// Only include files if updating them
if (newPdfFile) {
    updateData.append("journal_pdf", newPdfFile);
}
if (newCoverImage) {
    updateData.append("cover_image", newCoverImage);
}

const response = await fetch(
    `http://127.0.0.1:8000/api/v1/admin/journals/${journalId}`,
    {
        method: "PUT",
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
        body: updateData,
    }
);
```

### 3. **Get Journals**

```javascript
// Get all published journals (public)
const response = await fetch("http://127.0.0.1:8000/api/v1/journals");
const data = await response.json();

// Get all journals (admin)
const adminResponse = await fetch(
    "http://127.0.0.1:8000/api/v1/admin/journals",
    {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    }
);
const adminData = await adminResponse.json();
```

## 📋 **Response Format**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "title": "Sustainable Development in Education",
        "description": "Exploring sustainable practices...",
        "publication_date": "06/15/2024",
        "category": "research",
        "content": "Additional content...",
        "excerpt": "Short excerpt...",
        "status": "draft",
        "journal_pdf": "journals/pdfs/filename.pdf",
        "journal_pdf_url": "https://your-s3-bucket.s3.amazonaws.com/journals/pdfs/filename.pdf",
        "cover_image": "journals/covers/filename.jpg",
        "cover_image_url": "https://your-s3-bucket.s3.amazonaws.com/journals/covers/filename.jpg",
        "featured_image": "journals/filename.jpg",
        "featured_image_url": "https://your-s3-bucket.s3.amazonaws.com/journals/filename.jpg",
        "published_at": null,
        "created_at": "2024-10-29T03:25:00.000000Z",
        "updated_at": "2024-10-29T03:25:00.000000Z"
    }
}
```

## 🎯 **Key Features**

-   ✅ **PDF Upload** - Required journal PDF file
-   ✅ **Cover Image** - Optional cover image
-   ✅ **Featured Image** - Optional featured image
-   ✅ **No Author Field** - Removed as requested
-   ✅ **No Tags Field** - Removed as requested
-   ✅ **S3 Storage** - All files stored in S3
-   ✅ **Full URLs** - Backend adds complete S3 URLs
-   ✅ **Status Management** - Draft/Published status
-   ✅ **Category Support** - Optional category field

## 🔧 **File Storage Structure**

```
S3 Bucket:
├── journals/
│   ├── pdfs/           # Journal PDF files
│   ├── covers/         # Cover images
│   └── [featured].jpg  # Featured images
```

## 📝 **Form Validation Rules**

-   `title`: Required, max 255 characters
-   `description`: Required, text field
-   `journal_pdf`: Required, PDF file, max 10MB
-   `cover_image`: Optional, image file, max 2MB
-   `featured_image`: Optional, image file, max 2MB
-   `publication_date`: Optional, string
-   `category`: Optional, max 255 characters
-   `status`: Must be 'draft' or 'published'

The journal API is now updated to match your form structure! 🎉
