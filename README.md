# Cloud Analytics Service

خدمة سحابية متقدمة لتحليل البيانات الأساسية وإدارة المستندات باستخدام Google Drive API.

## نظرة عامة

هذا المشروع عبارة عن نظام شامل لإدارة وتحليل مجموعات كبيرة من المستندات (PDF و Word) مع وظائف متقدمة للبحث والفرز والتصنيف التلقائي. يستخدم النظام Google Drive كمنصة تخزين سحابية ويوفر واجهة مستخدم عربية سهلة الاستخدام.

## الميزات الرئيسية

### 🔄 إدارة المستندات
- رفع المستندات يدوياً أو مزامنة تلقائية من Google Drive
- دعم ملفات PDF، DOC، و DOCX
- استخراج تلقائي للنصوص والعناوين
- تخزين آمن في السحابة

### 🔍 البحث المتقدم
- بحث سريع بالكلمات المفتاحية
- البحث في العناوين والمحتوى
- تمييز النتائج المطابقة
- حفظ تاريخ البحث مع الإحصائيات

### 📊 الفرز والتنظيم
- فرز حسب العنوان، اسم الملف، التاريخ، التصنيف، أو الحجم
- دعم الفرز التصاعدي والتنازلي
- عرض مفصل للنتائج مع المعلومات الوصفية

### 🏷️ التصنيف الذكي
- تصنيف تلقائي باستخدام خوارزميات الكلمات المفتاحية
- شجرة تصنيف قابلة للتخصيص
- حساب مستوى الثقة في التصنيف
- إمكانية إعادة التصنيف

### 📈 الإحصائيات والتحليل
- إحصائيات شاملة عن المجموعة
- قياس أوقات تنفيذ العمليات
- تحليل توزيع المستندات
- مراقبة الأداء

## التقنيات المستخدمة

### Backend
- **PHP 8.1+** - لغة البرمجة الأساسية
- **Google Drive API** - للتكامل السحابي
- **SQLite** - قاعدة البيانات المحلية
- **Composer** - إدارة التبعيات

### Frontend
- **HTML5 & CSS3** - هيكل وتصميم الواجهة
- **JavaScript (Vanilla)** - التفاعل والديناميكية
- **Bootstrap 5** - إطار عمل CSS متجاوب
- **Font Awesome** - مكتبة الأيقونات

### المكتبات الرئيسية
- `google/apiclient` - Google APIs Client
- `smalot/pdfparser` - معالجة ملفات PDF
- `phpoffice/phpword` - معالجة ملفات Word
- `monolog/monolog` - تسجيل الأحداث

## متطلبات النظام

- PHP 8.1 أو أحدث
- Composer
- SQLite
- خادم ويب (Apache/Nginx)
- حساب Google Cloud Platform مع Drive API مفعل

## التثبيت السريع

```bash
# استنساخ المشروع
git clone https://github.com/your-username/cloud-analytics-service.git
cd cloud-analytics-service

# تثبيت التبعيات
composer install

# إعداد الصلاحيات
chmod 777 uploads/ logs/ data/ config/

# تشغيل الخادم للتطوير
php -S localhost:8000 -t public/
```

للحصول على دليل تثبيت مفصل، راجع [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md).

## الاستخدام

### 1. المصادقة
- افتح التطبيق في المتصفح
- انقر على "تسجيل الدخول بـ Google"
- أكمل عملية المصادقة

### 2. رفع المستندات
- استخدم تبويب "رفع المستندات"
- اختر ملف PDF أو Word
- انقر على "رفع المستند"

### 3. المزامنة
- انقر على "بدء المزامنة" لجلب الملفات من Google Drive
- سيتم معالجة الملفات الجديدة تلقائياً

### 4. البحث
- أدخل الكلمات المفتاحية في تبويب "البحث"
- اختر البحث في المحتوى أو العناوين فقط
- اعرض النتائج مع التمييز

### 5. الفرز والتصنيف
- استخدم تبويب "الفرز" لترتيب المستندات
- راجع تبويب "التصنيف" لتصنيف تلقائي
- اعرض الإحصائيات في التبويب المخصص

## هيكل المشروع

```
cloud-analytics-service/
├── config/                 # ملفات التكوين
│   ├── credentials.json    # بيانات اعتماد Google
│   └── token.json         # رموز المصادقة
├── data/                  # قاعدة البيانات
│   └── documents.db       # قاعدة بيانات SQLite
├── logs/                  # ملفات السجل
│   └── app.log           # سجل التطبيق
├── public/               # الملفات العامة
│   ├── index.html        # الواجهة الرئيسية
│   ├── app.js           # JavaScript للعميل
│   └── api.php          # نقاط النهاية API
├── src/                 # الكود المصدري
│   ├── CloudAnalyticsService.php
│   ├── GoogleDriveManager.php
│   ├── DocumentProcessor.php
│   └── DatabaseManager.php
├── uploads/             # ملفات مؤقتة
├── vendor/              # مكتبات Composer
├── composer.json        # تبعيات المشروع
├── project_report.md    # تقرير المشروع
├── INSTALLATION_GUIDE.md # دليل التثبيت
└── README.md           # هذا الملف
```

## API Documentation

### نقاط النهاية المتاحة

| النهاية | الطريقة | الوصف |
|---------|---------|--------|
| `/api.php/auth` | GET | التحقق من المصادقة |
| `/api.php/upload` | POST | رفع مستند جديد |
| `/api.php/sync` | POST | مزامنة مع Google Drive |
| `/api.php/search` | POST | البحث في المستندات |
| `/api.php/sort` | GET | فرز المستندات |
| `/api.php/classify` | POST | تصنيف المستندات |
| `/api.php/statistics` | GET | الحصول على الإحصائيات |

### مثال على الاستخدام

```javascript
// البحث في المستندات
const searchResponse = await fetch('/api.php/search', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        keywords: 'البحث المطلوب',
        search_in_content: true
    })
});

const results = await searchResponse.json();
console.log(results);
```

## المساهمة

نرحب بالمساهمات! يرجى اتباع الخطوات التالية:

1. Fork المشروع
2. إنشاء فرع للميزة الجديدة (`git checkout -b feature/amazing-feature`)
3. Commit التغييرات (`git commit -m 'Add amazing feature'`)
4. Push إلى الفرع (`git push origin feature/amazing-feature`)
5. فتح Pull Request

## الترخيص

هذا المشروع مرخص تحت رخصة MIT. راجع ملف [LICENSE](LICENSE) للتفاصيل.

## الدعم

إذا واجهت أي مشاكل أو لديك أسئلة:

- افتح [Issue جديد](https://github.com/your-username/cloud-analytics-service/issues)
- راجع [دليل التثبيت](INSTALLATION_GUIDE.md)
- اقرأ [تقرير المشروع](project_report.md) للتفاصيل التقنية

## الشكر والتقدير

- [Google Drive API](https://developers.google.com/drive/api) للتكامل السحابي
- [Bootstrap](https://getbootstrap.com/) لإطار عمل CSS
- [Font Awesome](https://fontawesome.com/) للأيقونات
- [PHP Community](https://www.php.net/) للمكتبات والأدوات

---

**تم تطويره بواسطة:** عز الدين منتصر الكحلوت 120222605  
**للمساق:** أنظمة السحابة والحوسبة الموزعة (SICT 4313)  
**الجامعة الإسلامية - غزة**  
**تاريخ الإنشاء:** يونيو 2025

#
