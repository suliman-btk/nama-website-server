# AWS S3 Configuration Guide

## ✅ **S3 Driver Fixed!**

The missing S3 driver has been installed. Now you need to configure your AWS credentials.

## 🔧 **Required Packages Installed:**

-   ✅ `league/flysystem-aws-s3-v3` - S3 filesystem driver
-   ✅ `aws/aws-sdk-php` - AWS SDK for PHP

## 📝 **Environment Configuration**

Add these variables to your `.env` file:

```env
# AWS S3 Configuration
AWS_ACCESS_KEY_ID=your_access_key_here
AWS_SECRET_ACCESS_KEY=your_secret_key_here
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
AWS_USE_PATH_STYLE_ENDPOINT=false

# Optional: Custom S3 endpoint (for DigitalOcean Spaces, etc.)
# AWS_ENDPOINT=https://nyc3.digitaloceanspaces.com
```

## 🚀 **Getting AWS Credentials**

### **Option 1: AWS Console**

1. Go to [AWS IAM Console](https://console.aws.amazon.com/iam/)
2. Create a new user with programmatic access
3. Attach policy: `AmazonS3FullAccess` (or create custom policy)
4. Copy Access Key ID and Secret Access Key

### **Option 2: AWS CLI**

```bash
aws configure
# Enter your credentials when prompted
```

### **Option 3: Alternative S3 Services**

-   **DigitalOcean Spaces** - Use Spaces endpoint
-   **Cloudflare R2** - S3-compatible API
-   **MinIO** - Self-hosted S3-compatible storage

## 🧪 **Test S3 Connection**

```bash
# Test if S3 is working
php artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello S3!');
>>> Storage::disk('s3')->get('test.txt');
```

## 📁 **S3 Bucket Structure**

Your files will be stored as:

```
your-bucket/
├── events/
│   ├── featured/
│   │   └── abc123.jpg
│   └── gallery/
│       ├── def456.jpg
│       └── ghi789.jpg
├── journals/
│   └── journal123.jpg
└── applications/
    └── resumes/
        └── resume123.pdf
```

## 🔒 **Security Notes**

1. **Never commit** `.env` file to version control
2. **Use IAM roles** in production (not access keys)
3. **Set bucket permissions** appropriately
4. **Enable CORS** if needed for frontend uploads

## ✅ **Ready to Use!**

Once configured, your CMS will automatically:

-   Upload event images to S3
-   Store gallery images in S3
-   Handle file management through Laravel's Storage facade
-   Provide secure, scalable file storage

Your CMS is now ready for production file storage! 🎉















