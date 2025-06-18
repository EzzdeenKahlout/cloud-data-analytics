# دليل التثبيت والتشغيل - خدمة تحليل البيانات السحابية

## متطلبات النظام

### متطلبات الخادم
- PHP 8.1 أو أحدث
- Composer (مدير التبعيات)
- خادم ويب (Apache/Nginx)
- SQLite
- اتصال إنترنت مستقر

### متطلبات Google Cloud
- حساب Google Cloud Platform
- مشروع مفعل مع Google Drive API
- بيانات اعتماد OAuth 2.0

## خطوات التثبيت

### 1. إعداد البيئة

```bash
# تحديث النظام
sudo apt update && sudo apt upgrade -y

# تثبيت PHP والمكونات المطلوبة
sudo apt install -y php php-cli php-curl php-json php-mbstring php-xml php-zip php-sqlite3

# تثبيت Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# تثبيت خادم الويب (اختياري للتطوير)
sudo apt install -y apache2
```

### 2. تحضير المشروع

```bash
# إنشاء مجلد المشروع
mkdir cloud-analytics-service
cd cloud-analytics-service

# استخراج ملفات المشروع
unzip cloud-analytics-service.zip
# أو
tar -xzf cloud-analytics-service.tar.gz

# تثبيت التبعيات
composer install

# إعداد الصلاحيات
chmod 755 public/
chmod 777 uploads/ logs/ data/ config/
```

### 3. إعداد Google Cloud Platform

#### 3.1 إنشاء مشروع جديد
1. اذهب إلى [Google Cloud Console](https://console.cloud.google.com/)
2. انقر على "إنشاء مشروع" أو اختر مشروع موجود
3. اكتب اسم المشروع واختر المؤسسة

#### 3.2 تفعيل Google Drive API
1. في لوحة التحكم، اذهب إلى "APIs & Services" > "Library"
2. ابحث عن "Google Drive API"
3. انقر على "تفعيل" (Enable)

#### 3.3 إنشاء بيانات الاعتماد
1. اذهب إلى "APIs & Services" > "Credentials"
2. انقر على "إنشاء بيانات اعتماد" > "OAuth client ID"
3. اختر "Web application"
4. أضف URIs المصرح بها:
   - `http://localhost:8000` (للتطوير)
   - `https://yourdomain.com` (للإنتاج)
5. حمل ملف JSON واحفظه كـ `config/credentials.json`

#### 3.4 تكوين شاشة الموافقة
1. اذهب إلى "OAuth consent screen"
2. اختر "External" للاختبار أو "Internal" للاستخدام الداخلي
3. املأ المعلومات المطلوبة
4. أضف النطاقات (Scopes):
   - `https://www.googleapis.com/auth/drive.file`
   - `https://www.googleapis.com/auth/drive.readonly`

### 4. تكوين التطبيق

#### 4.1 ملف التكوين الرئيسي
أنشئ ملف `config/config.php`:

```php
<?php
return [
    'app' => [
        'name' => 'Cloud Analytics Service',
        'version' => '1.0.0',
        'debug' => false, // true للتطوير
    ],
    'database' => [
        'path' => __DIR__ . '/../data/documents.db'
    ],
    'google' => [
        'credentials_path' => __DIR__ . '/credentials.json',
        'token_path' => __DIR__ . '/token.json'
    ],
    'upload' => [
        'max_size' => 100 * 1024 * 1024, // 100MB
        'allowed_types' => ['pdf', 'doc', 'docx'],
        'temp_dir' => __DIR__ . '/../uploads/'
    ],
    'logging' => [
        'level' => 'INFO',
        'file' => __DIR__ . '/../logs/app.log'
    ]
];
```

#### 4.2 ملف البيئة (اختياري)
أنشئ ملف `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret

DB_PATH=data/documents.db
LOG_LEVEL=INFO
```

### 5. تشغيل التطبيق

#### 5.1 للتطوير
```bash
# تشغيل خادم PHP المدمج
php -S localhost:8000 -t public/

# أو باستخدام المنفذ المحدد
php -S 0.0.0.0:8080 -t public/
```

#### 5.2 للإنتاج مع Apache

إنشاء ملف `.htaccess` في مجلد `public/`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# أمان إضافي
<Files "*.json">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

تكوين Virtual Host في Apache:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/cloud-analytics-service/public
    
    <Directory /var/www/cloud-analytics-service/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/cloud-analytics-error.log
    CustomLog ${APACHE_LOG_DIR}/cloud-analytics-access.log combined
</VirtualHost>
```

#### 5.3 للإنتاج مع Nginx

تكوين Nginx:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/cloud-analytics-service/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|json|log) {
        deny all;
    }
}
```

## الاختبار والتحقق

### 1. اختبار التثبيت الأساسي

```bash
# التحقق من إصدار PHP
php --version

# التحقق من المكتبات المثبتة
composer show

# اختبار الاتصال بقاعدة البيانات
php -r "
$pdo = new PDO('sqlite:data/documents.db');
echo 'Database connection successful' . PHP_EOL;
"
```

### 2. اختبار واجهة المستخدم

1. افتح المتصفح واذهب إلى `http://localhost:8000`
2. يجب أن تظهر صفحة تسجيل الدخول
3. انقر على "تسجيل الدخول بـ Google"
4. أكمل عملية المصادقة
5. تحقق من ظهور الواجهة الرئيسية

### 3. اختبار الوظائف الأساسية

```bash
# اختبار API
curl -X GET http://localhost:8000/api.php/auth

# اختبار رفع ملف (بعد المصادقة)
curl -X POST -F "document=@test.pdf" http://localhost:8000/api.php/upload

# اختبار البحث
curl -X POST -H "Content-Type: application/json" \
     -d '{"keywords":"test","search_in_content":true}' \
     http://localhost:8000/api.php/search
```

## استكشاف الأخطاء

### مشاكل شائعة وحلولها

#### 1. خطأ "Class not found"
```bash
# تأكد من تثبيت التبعيات
composer install

# إعادة إنشاء autoloader
composer dump-autoload
```

#### 2. خطأ في الصلاحيات
```bash
# إعطاء صلاحيات الكتابة
sudo chown -R www-data:www-data /var/www/cloud-analytics-service/
sudo chmod -R 755 /var/www/cloud-analytics-service/
sudo chmod -R 777 /var/www/cloud-analytics-service/uploads/
sudo chmod -R 777 /var/www/cloud-analytics-service/logs/
sudo chmod -R 777 /var/www/cloud-analytics-service/data/
```

#### 3. خطأ في Google API
```bash
# التحقق من ملف credentials.json
cat config/credentials.json | jq .

# التحقق من تفعيل API
# اذهب إلى Google Cloud Console وتأكد من تفعيل Drive API
```

#### 4. مشاكل قاعدة البيانات
```bash
# إنشاء قاعدة البيانات يدوياً
sqlite3 data/documents.db < sql/schema.sql

# التحقق من الجداول
sqlite3 data/documents.db ".tables"
```

### تسجيل الأخطاء

تحقق من ملفات السجل:

```bash
# سجل التطبيق
tail -f logs/app.log

# سجل خادم الويب
tail -f /var/log/apache2/error.log
# أو
tail -f /var/log/nginx/error.log

# سجل PHP
tail -f /var/log/php8.1-fpm.log
```

## الصيانة والتحديث

### النسخ الاحتياطية

```bash
#!/bin/bash
# backup.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/cloud-analytics-$DATE"

mkdir -p $BACKUP_DIR

# نسخ قاعدة البيانات
cp data/documents.db $BACKUP_DIR/

# نسخ ملفات التكوين
cp -r config/ $BACKUP_DIR/

# نسخ السجلات
cp -r logs/ $BACKUP_DIR/

# ضغط النسخة الاحتياطية
tar -czf $BACKUP_DIR.tar.gz $BACKUP_DIR/
rm -rf $BACKUP_DIR/

echo "Backup created: $BACKUP_DIR.tar.gz"
```

### تحديث التطبيق

```bash
#!/bin/bash
# update.sh

# إيقاف الخدمة
sudo systemctl stop apache2

# نسخ احتياطية
./backup.sh

# تحديث الكود
git pull origin main

# تحديث التبعيات
composer update

# تشغيل migrations (إن وجدت)
php scripts/migrate.php

# إعادة تشغيل الخدمة
sudo systemctl start apache2

echo "Update completed successfully"
```

### مراقبة الأداء

```bash
#!/bin/bash
# monitor.sh

# مراقبة استخدام القرص
df -h

# مراقبة استخدام الذاكرة
free -h

# مراقبة العمليات
ps aux | grep php

# حجم قاعدة البيانات
ls -lh data/documents.db

# عدد الملفات المرفوعة
ls uploads/ | wc -l

# آخر 10 أخطاء
tail -10 logs/app.log | grep ERROR
```

## الأمان

### إعدادات الأمان الموصى بها

1. **تشفير HTTPS:**
```bash
# تثبيت Let's Encrypt
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d yourdomain.com
```

2. **جدار الحماية:**
```bash
# تفعيل UFW
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

3. **تحديث النظام:**
```bash
# تحديثات أمنية تلقائية
sudo apt install unattended-upgrades
sudo dpkg-reconfigure unattended-upgrades
```

4. **حماية الملفات الحساسة:**
```apache
# في .htaccess
<Files "credentials.json">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>
```

## الدعم والمساعدة

### معلومات الاتصال
- **البريد الإلكتروني:** [your-email@domain.com]
- **الموقع الإلكتروني:** [https://your-website.com]
- **GitHub:** [https://github.com/your-username/cloud-analytics-service]

### الموارد المفيدة
- [Google Drive API Documentation](https://developers.google.com/drive/api)
- [PHP Manual](https://www.php.net/manual/)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Bootstrap Documentation](https://getbootstrap.com/docs/)

---

**تاريخ آخر تحديث:** 17 يونيو 2025  
**إصدار الدليل:** 1.0

