# ระบบ Lift Display - Canvas 39
## สรุปรายละเอียดระบบและฟีเจอร์ทั้งหมด

---

## 🖥️ **ระบบแสดงผลหน้าจอลิฟต์ (Lift Display System)**

### **หน้าจอแสดงผลหลัก (lift-display.html)**
ระบบแสดงผลข้อมูลแบบเรียลไทม์บนหน้าจอลิฟต์ โดยมีการออกแบบที่ทันสมัยและ responsive สำหรับทุกขนาดหน้าจอ

#### **ส่วนประกอบของหน้าจอ:**

1. **Header Section (ส่วนหัว - สีน้ำตาล)**
   - โลโก้บริษัท/โครงการ (สามารถอัปโหลดเปลี่ยนได้)
   - วันที่และเวลาปัจจุบัน (แสดงแบบเรียลไทม์)
   - ระบบนาฬิกาอัตโนมัติ (โซนเวลาไทย)

2. **Building Section (ส่วนแสดงภาพอาคาร)**
   - แสดงภาพอาคาร/โครงการ (สามารถอัปโหลดเปลี่ยนได้)
   - ระบบแสดงประกาศฉุกเฉิน (Notice Overlay)
   - พื้นหลังสีฟ้าเมื่อไม่มีรูปภาพ

3. **Weather Section (ส่วนสภาพอากาศ - สีเบจ)**
   - สภาพอากาศวันนี้ (อุณหภูมิต่ำสุด-สูงสุด, ไอคอนสภาพอากาศ)
   - พยากรณ์อากาศ 4 ชั่วโมงข้างหน้า
   - ข้อมูลจาก OpenWeatherMap API

4. **AQI Section (ส่วนคุณภาพอากาศ - สีเบจเข้ม)**
   - **Indoor AQI** (คุณภาพอากาศภายใน - สีเขียว)
     - ค่า AQI, สถานะ (Good/Moderate/Unhealthy)
     - สารมลพิษหลัก และความเข้มข้น
     - ไอคอนแสดงสถานะ
   - **Outdoor AQI** (คุณภาพอากาศภายนอก - สีเหลือง)
     - ข้อมูลจากสถานี Canvas 39
     - การแสดงผลแบบเรียลไทม์

5. **Pool Section (ส่วนข้อมูลสระว่ายน้ำ)**
   - **WATER (น้ำใช้)**
     - pH, Chlorine, Temperature
   - **SWIMMING POOL (สระว่ายน้ำ)**
     - pH, Chlorine, Alkalinity, Temperature
   - การ์ดแสดงผลสีสันตาม mockup design

---

## ⚙️ **ระบบจัดการหลังบ้าน (Backend System)**

### **1. Admin Panel (lift-admin.html)**
หน้าจัดการระบบสำหรับผู้ดูแล

#### **ฟีเจอร์การจัดการ:**
- **อัปโหลดโลโก้และรูปภาพอาคาร**
  - Drag & Drop Interface
  - Preview แบบเรียลไทม์
  - รองรับไฟล์ JPG, PNG
  - ขนาดไฟล์สูงสุด 10MB

- **จัดการประกาศ (Notice Management)**
  - สร้างประกาศฉุกเฉิน
  - กำหนดเวลาเริ่มต้น-สิ้นสุด
  - เปิด/ปิดการแสดงผล
  - แก้ไขข้อความประกาศ

- **ตั้งค่าระบบ**
  - API Keys สำหรับ Weather
  - การตั้งค่าแหล่งข้อมูล AQI
  - พิกัดที่ตั้งสำหรับข้อมูลสภาพอากาศ

### **2. API System (โฟลเดอร์ api/)**

#### **upload-image.php**
- จัดการการอัปโหลดรูปภาพ
- บันทึกในระบบฐานข้อมูล
- จัดเก็บไฟล์ในโฟลเดอร์ uploads/
- ระบบรักษาความปลอดภัย

#### **lift-display-data.php**
- API หลักสำหรับข้อมูลหน้าจอลิฟต์
- รวมข้อมูล Weather, AQI, Pool
- การจัดการข้อมูล JSON format
- ระบบ fallback เมื่อ API ล้มเหลว

#### **air-quality-multi.php**
- ระบบ Multi-source AQI
- รองรับ 3 แหล่งข้อมูล:
  - Device API (เซนเซอร์ตรง)
  - Public Station (Canvas 39)
  - City API (ข้อมูลเมือง)
- ระบบ Priority และ Fallback
- การตรวจสอบความถูกต้องของข้อมูล

#### **get-notices.php**
- จัดการประกาศที่แสดงบนหน้าจอ
- ตรวจสอบเวลาแสดงผล
- ส่งข้อมูลในรูปแบบ JSON

#### **config.php**
- การตั้งค่าฐานข้อมูล
- API Keys และ URLs
- ฟังก์ชันช่วยเหลือทั่วไป

### **3. Database System**
ฐานข้อมูล MySQL พร้อมตารางต่างๆ:

- **lift_display_settings** - การตั้งค่าระบบ
- **lift_display_images** - จัดเก็บรูปภาพ
- **lift_display_notices** - ประกาศและข่าวสาร

---

## 📱 **Responsive Design**

### **รองรับอุปกรณ์:**
- **Desktop/TV Screens** (1920px+)
- **iPad Pro 12.9"** (Portrait & Landscape)
- **iPad Air/Standard Tablets** (768x1024)
- **Mobile Devices** (Portrait & Landscape)

### **เทคโนโลยีที่ใช้:**
- CSS Grid และ Flexbox
- Viewport Height Optimization
- Touch-friendly Interface
- Auto-scaling Typography

---

## 🔄 **ระบบอัตโนมัติ**

### **การอัปเดตข้อมูล:**
- **เวลา**: อัปเดตทุกวินาที
- **ข้อมูล AQI**: อัปเดตทุก 5 นาที
- **สภาพอากาศ**: อัปเดตทุก 5 นาที
- **ประกาศ**: ตรวจสอบแบบเรียลไทม์

### **Keyboard Shortcuts:**
- `F` - เข้า/ออกโหมดเต็มจอ
- `N` - แสดงประกาศทดสอบ
- `H` - ซ่อนประกาศ
- `R` - รีโหลดข้อมูล

---

## 🛡️ **ความปลอดภัย**

### **มาตรการรักษาความปลอดภัย:**
- การตรวจสอบประเภทไฟล์อัปโหลด
- SQL Injection Protection (PDO)
- XSS Protection
- CORS Headers
- Input Sanitization

---

## 🚀 **วิธีการใช้งาน**

### **สำหรับผู้ดูแลระบบ:**
1. เข้าใช้งาน Admin Panel ที่ `lift-admin.html`
2. อัปโหลดโลโก้และรูปภาพอาคาร
3. ตั้งค่า API Keys สำหรับ Weather
4. สร้างและจัดการประกาศ

### **สำหรับการแสดงผล:**
1. เปิด `lift-display.html` บนหน้าจอลิฟต์
2. กด F เพื่อเข้าโหมดเต็มจอ
3. ระบบจะแสดงผลและอัปเดตอัตโนมัติ

---

## 📋 **ข้อกำหนดระบบ**

### **Server Requirements:**
- PHP 7.4+ พร้อม PDO MySQL
- MySQL/MariaDB Database
- Web Server (Apache/Nginx)
- CURL Extension

### **Browser Support:**
- Chrome/Chromium (แนะนำ)
- Safari
- Firefox
- Edge

---

## 🎯 **จุดเด่นของระบบ**

✅ **การออกแบบสวยงาม** - ตาม Mockup ของลูกค้า  
✅ **Responsive ครบทุกอุปกরณ์** - รองรับ Tablet, TV, Mobile  
✅ **ข้อมูลเรียลไทม์** - AQI, Weather, Time  
✅ **ระบบจัดการง่าย** - Admin Panel ใช้งานง่าย  
✅ **ความปลอดภัยสูง** - มาตรฐานการรักษาความปลอดภัย  
✅ **ระบบสำรองข้อมูล** - Fallback เมื่อ API ล้มเหลว  
✅ **การแสดงประกาศ** - สำหรับข้อมูลฉุกเฉิน

---

**พัฒนาโดย:** Vipart Development Team  
**เวอร์ชัน:** 2.0  
**วันที่อัปเดต:** ธันวาคม 2024