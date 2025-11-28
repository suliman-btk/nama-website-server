# Event Creation with Gallery - API Documentation

## ✅ **Yes! You can create an event with galleries in a single request**

The API already supports creating events with gallery images in one request. Here's how:

## 📝 **API Endpoint**

```
POST /api/v1/admin/events
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

## 📋 **Request Body**

```json
{
    "title": "Foundation Gala 2024",
    "description": "Annual fundraising gala event",
    "short_description": "Join us for our annual gala",
    "start_date": "2024-12-15 18:00:00",
    "end_date": "2024-12-15 23:00:00",
    "location": "Grand Hotel Ballroom",
    "status": "published",
    "featured_image": "file_upload",
    "galleries": [
        {
            "image": "file_upload",
            "alt_text": "Gala setup"
        },
        {
            "image": "file_upload",
            "alt_text": "Guest arrival"
        },
        {
            "image": "file_upload",
            "alt_text": "Auction items"
        }
    ]
}
```

## 🔧 **How It Works**

1. **Event Creation** - Creates the main event record
2. **Featured Image** - Uploads to S3: `events/featured/`
3. **Gallery Images** - Uploads each to S3: `events/gallery/`
4. **Database Links** - Creates EventGallery records with sort order
5. **Response** - Returns event with all galleries included

## 📊 **Response Example**

```json
{
    "success": true,
    "message": "Event created successfully",
    "data": {
        "id": 1,
        "title": "Foundation Gala 2024",
        "description": "Annual fundraising gala event",
        "start_date": "2024-12-15T18:00:00.000000Z",
        "end_date": "2024-12-15T23:00:00.000000Z",
        "location": "Grand Hotel Ballroom",
        "status": "published",
        "featured_image": "events/featured/abc123.jpg",
        "galleries": [
            {
                "id": 1,
                "image_path": "events/gallery/def456.jpg",
                "alt_text": "Gala setup",
                "sort_order": 0
            },
            {
                "id": 2,
                "image_path": "events/gallery/ghi789.jpg",
                "alt_text": "Guest arrival",
                "sort_order": 1
            }
        ]
    }
}
```

## 🎯 **Benefits**

-   ✅ **Single Request** - Create event + galleries together
-   ✅ **S3 Storage** - All images stored in AWS S3
-   ✅ **Sort Order** - Gallery images ordered automatically
-   ✅ **Validation** - Full validation for all fields
-   ✅ **Relationships** - Proper database relationships

## 🚀 **Ready to Use!**

Your API already supports this functionality - no additional development needed!














