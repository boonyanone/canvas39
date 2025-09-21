# 🚨 API Files Recovery - เสร็จสิ้น

## ✅ สถานะ: กู้คืนไฟล์ API สำเร็จ

### 📁 ไฟล์ที่สร้างใหม่ทั้งหมด:

1. **`api/upload-image.php`** (11.3 KB)
   - จัดการอัปโหลดรูป Logo และ Building
   - จัดการการตั้งค่าระบบ
   - จัดการประกาศ (CRUD operations)
   - สร้างตารางฐานข้อมูลอัตโนมัติ

2. **`api/lift-display-data.php`** (10.2 KB)
   - ดึงข้อมูล AQI (Indoor/Outdoor)
   - ดึงข้อมูลสภาพอากาศ
   - รวมข้อมูลประกาศ
   - รองรับ OpenWeatherMap API

3. **`api/get-notices.php`** (1.1 KB)
   - Proxy สำหรับดึงประกาศ
   - เชื่อมต่อกับ upload-image.php

4. **`api/air-quality-multi.php`** (13.4 KB)
   - ระบบ Multi-source AQI เดิม
   - รองรับ Device API, Public Scraping, City API
   - Auto-fallback system

5. **`api/config.php`** (1.6 KB)
   - การตั้งค่าฐานข้อมูล
   - การตั้งค่า API keys
   - ฟังก์ชันช่วยเหลือ

6. **`api/test.php`** (ใหม่)
   - ทดสอบระบบ API
   - แสดงรายการ endpoints

### 🗂️ ตารางฐานข้อมูลที่จะสร้าง:

1. **`lift_display_settings`**
   - เก็บการตั้งค่าระบบ
   - logo_image, building_image paths

2. **`lift_display_images`** 
   - เก็บข้อมูลรูปภาพที่อัปโหลด
   - รองรับ versioning

3. **`lift_display_notices`**
   - เก็บประกาศ
   - รองรับ start_time/end_time

### 🔌 API Endpoints พร้อมใช้งาน:

#### Test API:
```
GET  /api/test.php
```

#### ข้อมูลหลัก:
```
GET  /api/lift-display-data.php
```

#### จัดการรูปภาพ:
```
POST /api/upload-image.php
Body: {"type": "logo|building", "image": "base64_data"}
```

#### ดูรูปที่อัปโหลดแล้ว:
```
POST /api/upload-image.php
Body: {"action": "get_images"}
```

#### จัดการประกาศ:
```
POST /api/upload-image.php
Body: {"action": "save_notice", "title": "...", "message": "..."}

POST /api/upload-image.php  
Body: {"action": "get_notices"}
```

#### AQI Multi-source:
```
POST /api/air-quality-multi.php
Body: {"sources": [...]}
```

### 🎯 ข้อมูล AQI ที่ดึงได้:

**Outdoor AQI Sources:**
1. Device API: `https://device.iqair.com/v2/6790850e7307e18fb3e0c815/validated-data`
2. Public Scraping: IQAir Canvas 39 page
3. City API: IQAir Bangkok (fallback)

**Indoor AQI:**
- ปัจจุบัน: ข้อมูลจำลอง (ดีกว่า outdoor 20-40%)
- **TODO**: เชื่อมต่อ indoor sensor จริง

### ⚙️ การตั้งค่าที่ต้องทำ:

1. **Weather API** (ใน `lift-display-data.php`):
```php
$apiKey = 'your-openweather-api-key'; // ใส่ API key จริง
```

2. **Database**: 
   - ระบบจะสร้างตารางอัตโนมัติเมื่อเรียกใช้ครั้งแรก
   - ใช้ข้อมูลเชื่อมต่อเดียวกับระบบเดิม

3. **Upload Directory**:
```bash
chmod 755 uploads/lift-display/
```

### 🧪 การทดสอบ:

```bash
# ทดสอบ API พื้นฐาน
curl http://your-domain/api/test.php

# ทดสอบดึงข้อมูล
curl http://your-domain/api/lift-display-data.php

# ทดสอบประกาศ
curl http://your-domain/api/get-notices.php
```

### 📱 หน้าที่ใช้งาน:

- **หน้าจอลิฟท์**: `lift-display.html`
- **Admin Panel**: `lift-admin.html`

### 🎉 สรุป:

✅ **ไฟล์ API ครบถ้วน** - สร้างใหม่ 6 ไฟล์  
✅ **ฟังก์ชันครบ** - อัปโหลดรูป, จัดการประกาศ, ดึงข้อมูล  
✅ **Database Ready** - สร้างตารางอัตโนมัติ  
✅ **Multi-source AQI** - รองรับ 3 แหล่งข้อมูล  
✅ **Weather Integration** - รองรับ OpenWeatherMap  

**ระบบพร้อมใช้งานแล้ว!** 🚀

---

*กู้คืนเสร็จเมื่อ: $(date)*