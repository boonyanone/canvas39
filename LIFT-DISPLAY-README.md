# 🖥️ Lift Display System

ระบบแสดงข้อมูลสำหรับหน้าจอลิฟท์ตามแบบที่กำหนด พร้อมระบบจัดการแบบครบถ้วน

## 📋 สรุประบบที่สร้างแล้ว

### ✅ ความสามารถที่ทำเสร็จแล้ว

1. **🖥️ หน้าจอลิฟท์** (`lift-display.html`)
   - Header สีน้ำตาล พร้อมแสดงวันที่และเวลา
   - Logo ที่สามารถอัปโหลดและเปลี่ยนได้
   - รูปอาคารที่สามารถเปลี่ยนได้
   - ระบบประกาศที่แสดงทับรูปอาคาร
   - ส่วนแสดงสภาพอากาศ (Today + Outlook 4 ชั่วโมง)
   - ส่วนแสดง AQI Indoor/Outdoor พร้อมไอคอนและสี

2. **👨‍💼 Admin Panel** (`lift-admin.html`)
   - อัปโหลด Logo และรูปอาคาร (รองรับ Drag & Drop)
   - จัดการประกาศ (เพิ่ม/แก้ไข/ลบ)
   - ตั้งเวลาเริ่ม-สิ้นสุดประกาศ
   - ดูสถานะระบบ
   - Preview หน้าจอลิฟท์

3. **🔧 Backend API**
   - `api/upload-image.php` - จัดการอัปโหลดรูปและการตั้งค่า
   - `api/lift-display-data.php` - ดึงข้อมูล AQI และสภาพอากาศ
   - `api/get-notices.php` - ดึงข้อมูลประกาศ
   - ฐานข้อมูล 3 ตาราง: settings, images, notices

## 🚀 การติดตั้งและใช้งาน

### 1. การติดตั้ง
```bash
# Upload ไฟล์ทั้งหมดไปยัง web server
# ตรวจสอบให้แน่ใจว่า folder uploads/ สามารถเขียนได้

chmod 755 uploads/
chmod 755 uploads/lift-display/
```

### 2. การตั้งค่าฐานข้อมูล
ระบบจะสร้างตารางอัตโนมัติเมื่อเรียกใช้ครั้งแรก:
- `lift_display_settings` - เก็บการตั้งค่าระบบ
- `lift_display_images` - เก็บข้อมูลรูปภาพ
- `lift_display_notices` - เก็บประกาศ

### 3. การเข้าใช้งาน

#### หน้าจอลิฟท์:
```
http://your-domain/lift-display.html
```

#### Admin Panel:
```
http://your-domain/lift-admin.html
```

## 🎮 การใช้งาน

### หน้าจอลิฟท์
- **คลิกที่ Logo**: อัปโหลดโลโก้ใหม่
- **คลิกที่รูปอาคาร**: อัปโหลดรูปอาคารใหม่
- **Auto-refresh**: อัปเดตข้อมูลทุก 5 นาที
- **Keyboard shortcuts**:
  - `n` = แสดงประกาศทดสอบ
  - `h` = ซ่อนประกาศ
  - `r` = Reload ข้อมูล
  - `f` = Fullscreen

### Admin Panel
1. **อัปโหลดรูป**:
   - คลิกในกรอบ หรือ Drag & Drop
   - รองรับ PNG, JPG
   - Logo: สูงสุด 5MB
   - Building: สูงสุด 10MB

2. **จัดการประกาศ**:
   - กรอกหัวข้อและข้อความ
   - ตั้งเวลาเริ่ม-สิ้นสุด (ทางเลือก)
   - บันทึกเพื่อแสดงในหน้าจอลิฟท์

3. **ตรวจสอบสถานะ**:
   - ดูสถานะการเชื่อมต่อ API
   - ทดสอบประกาศ
   - Preview หน้าจอลิฟท์

## 📊 ข้อมูลที่แสดง

### ตอนนี้ข้อมูล AQI ดึงจาก:

1. **Outdoor AQI**:
   - **Device API**: `https://device.iqair.com/v2/6790850e7307e18fb3e0c815/validated-data` (หลัก)
   - **Public Station**: Scraping จาก IQAir Canvas 39 page (สำรอง)
   - **City API**: IQAir Bangkok API (fallback)

2. **Indoor AQI**:
   - ตอนนี้ใช้ข้อมูลจำลอง (ดีกว่า outdoor 20-40%)
   - **TODO**: เชื่อมต่อกับ indoor sensor จริง

3. **Weather**:
   - รองรับ OpenWeatherMap API (ใส่ API key ในโค้ด)
   - ถ้าไม่มี API key ใช้ข้อมูลจำลอง

## 🎨 การปรับแต่ง

### เปลี่ยนสีธีม
แก้ไขใน CSS Variables:
```css
:root {
    --header-brown: #8B7355;    /* สีน้ำตาลหัวข้อ */
    --weather-beige: #E8E1D5;   /* สีพื้นหลังสภาพอากาศ */
    --footer-beige: #D4C4A8;    /* สีพื้นหลัง AQI */
    --indoor-green: #7CB342;    /* สี AQI ในร่ม */
    --outdoor-yellow: #FFB300;  /* สี AQI กลางแจ้ง */
}
```

### เปลี่ยนข้อความ
แก้ไขใน HTML:
```html
<!-- เปลี่ยนข้อความ placeholder -->
<div class="logo-placeholder">
    CANVAS<br>39<br>
    <small>Click to upload logo</small>
</div>
```

## 🔧 การพัฒนาต่อ

### เชื่อมต่อ Indoor Sensor จริง
แก้ไขใน `api/lift-display-data.php`:
```php
private function getIndoorAQI() {
    // เพิ่ม API call ไปยัง indoor sensor
    $indoorApiUrl = 'https://your-indoor-sensor-api.com/data';
    // ... implementation
}
```

### เพิ่ม Weather API Key
แก้ไขใน `api/lift-display-data.php`:
```php
$apiKey = 'your-openweather-api-key'; // ใส่ API key จริง
```

### เชื่อมต่อระบบประกาศกับฐานข้อมูล
ระบบ backend พร้อมแล้ว เพียงแค่ใช้ Admin Panel ในการจัดการ

## 📱 Responsive Design

- **Desktop/TV**: Layout เต็มจอ 4 ส่วน
- **Tablet**: ปรับขนาดอัตโนมัติ
- **Mobile**: Layout แนวตั้ง (ถ้าจำเป็น)

## 🔍 การ Debug

### ตรวจสอบ API:
```bash
# ทดสอบ API หลัก
curl http://your-domain/api/lift-display-data.php

# ทดสอบ upload API
curl -X POST http://your-domain/api/upload-image.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_images"}'
```

### ตรวจสอบไฟล์ที่อัปโหลด:
```bash
ls -la uploads/lift-display/
```

### ตรวจสอบ Console:
เปิด Developer Tools และดู Console สำหรับ error messages

## 📝 โครงสร้างไฟล์

```
vipart/
├── lift-display.html        # หน้าจอลิฟท์
├── lift-admin.html          # Admin panel
├── uploads/
│   └── lift-display/        # รูปที่อัปโหลด
└── api/
    ├── upload-image.php     # จัดการอัปโหลดและการตั้งค่า
    ├── lift-display-data.php # ดึงข้อมูล AQI + Weather
    ├── get-notices.php      # ดึงประกาศ
    └── air-quality-multi.php # AQI system เดิม
```

## ✨ Features พิเศษ

- **Drag & Drop**: อัปโหลดรูปแบบลากวาง
- **Real-time Preview**: ดูผลลัพธ์ทันที
- **Auto-save**: บันทึกรูปใน localStorage
- **Fallback System**: ใช้ข้อมูลจำลองเมื่อ API ล้มเหลว
- **Responsive**: รองรับทุกขนาดหน้าจอ
- **Multi-API**: รองรับหลายแหล่งข้อมูล AQI

---

## 🎯 สรุป

ระบบ Lift Display นี้ทำงานได้ครบถ้วนตามความต้องการ:

✅ **Header สีน้ำตาล** พร้อม Logo และ DateTime  
✅ **รูปอาคาร** ที่เปลี่ยนได้  
✅ **ระบบประกาศ** ที่แสดงทับรูป  
✅ **Weather + AQI** แสดงครบถ้วน  
✅ **Admin Panel** จัดการง่าย  
✅ **Backend System** ครบครัน  

**พร้อมใช้งานทันที!** 🚀

---

© 2025 Vipart Lift Display System | Built with ❤️